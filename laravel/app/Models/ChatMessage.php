<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_name',
        'message',
        'metadata',
        'is_internal',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_internal' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function getSenderDisplayName()
    {
        if ($this->sender_name) {
            return $this->sender_name;
        }

        switch ($this->sender_type) {
            case 'ai':
                // Prefer organization-specific assistant display name when available
                try {
                    if ($this->conversation && $this->conversation->organization && isset($this->conversation->organization->settings['assistant_display_name'])) {
                        $name = (string) $this->conversation->organization->settings['assistant_display_name'];
                        if (trim($name) !== '') {
                            return $name;
                        }
                    }
                } catch (\Throwable $e) { /* ignore and fallback */ }
                return 'AI Assistant';
            case 'agent':
                return 'Support Agent';
            case 'user':
                return $this->conversation->getDisplayName();
            default:
                return 'Unknown';
        }
    }

    public function isFromUser()
    {
        return $this->sender_type === 'user';
    }

    public function isFromAI()
    {
        return $this->sender_type === 'ai';
    }

    public function isFromAgent()
    {
        return $this->sender_type === 'agent';
    }

    /**
     * Accessor to return a safe HTML version of the message with clickable links.
     */
    public function getMessageHtmlAttribute()
    {
        if (!$this->message) {
            return '';
        }
        $text = (string) $this->message;
        if ($text === '') return '';

        // Preserve existing anchors
        $anchorPlaceholders = [];
        $text = preg_replace_callback('/<a\b[^>]*>.*?<\/a>/i', function($m) use (&$anchorPlaceholders){
            $anchorPlaceholders[] = $m[0];
            return '__ANCHOR_'.(count($anchorPlaceholders)-1).'__';
        }, $text) ?? $text;

    // Strip remaining HTML tags defensively
    $text = preg_replace('/<[^>]*>/', '', $text) ?? $text;

    // Clean up legacy attribute fragments that may exist as plain text from older stored messages
    // Examples: target="_blank" rel="nofollow noopener noreferer" (typo variant) or rel="nofollow noopener noreferrer"
    $text = preg_replace('/\b(target|rel)\s*=\s*"[^"]*"/i', '', $text) ?? $text;
    $text = preg_replace("/\b(target|rel)\s*=\s*'[^']*'/i", '', $text) ?? $text;
    // Remove stray '>' that could be left from malformed tags
    $text = preg_replace('/\s*>\s*/', ' ', $text) ?? $text;

    // === Numbered list normalisation (mirrors JS linkify() in widget/script.blade.php) ===
    // Case 1: number at end of previous sentence without a proper newline
    $text = preg_replace('/([^\n])\s*(\d+)\.\s*\n/u', "$1\n\n$2. ", $text);
    // Case 2: number starts a line without a blank line before it
    $text = preg_replace('/\n(\d+\.\s)/u', "\n\n$1", $text);
    // Case 3: fully inline list — e.g. "...conversations) 2. Basic"
    $text = preg_replace('/([.!?:,)])\s+(\d+\.\s+)(?=\D)/u', "$1\n\n$2", $text);

    // Escape early to prevent XSS; we'll inject safe anchors later
    $escaped = e($text);

    // === Markdown bold / italic ===
    $boldTokens = [];
    $escaped = preg_replace_callback('/\*\*([^*\n]+?)\*\*/u', function ($m) use (&$boldTokens) {
        $boldTokens[] = $m[1]; // content is already e()-escaped
        return '__BOLD_' . (count($boldTokens) - 1) . '__';
    }, $escaped) ?? $escaped;
    $italicTokens = [];
    $escaped = preg_replace_callback('/(?<!\*)\*([^*\n]+?)\*(?!\*)/u', function ($m) use (&$italicTokens) {
        $italicTokens[] = $m[1];
        return '__ITALIC_' . (count($italicTokens) - 1) . '__';
    }, $escaped) ?? $escaped;

        // Placeholder emails to ensure they are NOT linked
        $emailPlaceholders = [];
        $escaped = preg_replace_callback('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', function($m) use (&$emailPlaceholders){
            $emailPlaceholders[] = $m[0];
            return '__EMAIL_'.(count($emailPlaceholders)-1).'__';
        }, $escaped) ?? $escaped;

        // Work on a mutable copy
        $processed = $escaped;

        // First pass: linkify http/https URLs with punctuation trimming
        $processed = preg_replace_callback('/https?:\/\/[^\s<]+/i', function ($matches) {
            // The input string is already HTML-escaped, so decode to get the raw URL before building the anchor
            $full = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $trail = '';
            while (preg_match('/[\",\.!?)]$/', $full)) {
                $trail = substr($full, -1) . $trail;
                $full = substr($full, 0, -1);
            }
            $display = strlen($full) > 80 ? substr($full,0,77).'...' : $full;
            return '<a href="'.e($full).'" target="_blank" rel="nofollow noopener noreferrer">'.e($display).'</a>'.$trail;
        }, $processed) ?? $processed;

        // Protect anchors created so far to avoid matching inside their href attributes in the next pass
        $createdAnchorPlaceholders = [];
        $processed = preg_replace_callback('/<a\b[^>]*>.*?<\/a>/i', function($m) use (&$createdAnchorPlaceholders){
            $createdAnchorPlaceholders[] = $m[0];
            return '__ANCHOR_GEN_'.(count($createdAnchorPlaceholders)-1).'__';
        }, $processed) ?? $processed;

        // Second pass: linkify bare domains while avoiding emails and not touching protected anchors
        $processed = preg_replace_callback("~(^|[^@\\w:/])((?:[a-zA-Z0-9-]+\\.)+[a-zA-Z]{2,})(/[\\w\\-\\._\\~:/?#\\[\\]@!$&'()*+,;=%]*)?~i", function($m){
            $pre = $m[1];
            $host = $m[2];
            $path = html_entity_decode($m[3] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Trim trailing quotes or punctuation accidentally captured in path
            $suffix = '';
            while ($path !== '' && preg_match("/[\"'\.,!?)]$/", $path)) {
                $suffix = substr($path, -1) . $suffix;
                $path = substr($path, 0, -1);
            }
            $url = 'https://' . $host . $path;
            return e($pre).'<a href="'.e($url).'" target="_blank" rel="nofollow noopener noreferrer">'.e($host.$path).'</a>'.$suffix;
        }, $processed) ?? $processed;

        // Restore generated anchors
        foreach ($createdAnchorPlaceholders as $i => $html) {
            $processed = str_replace('__ANCHOR_GEN_'.$i.'__', $html, $processed);
        }

        // Restore anchors present in the original message
        foreach ($anchorPlaceholders as $i => $html) {
            $processed = str_replace('__ANCHOR_'.$i.'__', $html, $processed);
        }

        // Restore email placeholders as plain text (no link)
        foreach ($emailPlaceholders as $i => $email) {
            $processed = str_replace('__EMAIL_'.$i.'__', e($email), $processed);
        }

        // Restore bold / italic markdown
        foreach ($boldTokens as $i => $inner) {
            $processed = str_replace('__BOLD_'.$i.'__', '<strong>'.$inner.'</strong>', $processed);
        }
        foreach ($italicTokens as $i => $inner) {
            $processed = str_replace('__ITALIC_'.$i.'__', '<em>'.$inner.'</em>', $processed);
        }

        // === Convert to paragraphs and numbered lists ===
        $paragraphs = preg_split('/\n\n+/', $processed);
        if ($paragraphs === false || count($paragraphs) <= 1) {
            return nl2br($processed);
        }
        $htmlParts = [];
        $listItems = [];
        $flushList = function () use (&$listItems, &$htmlParts) {
            if (!empty($listItems)) {
                $liHtml = implode('', array_map(fn ($item) => '<li>' . $item . '</li>', $listItems));
                $htmlParts[] = '<ol>' . $liHtml . '</ol>';
                $listItems = [];
            }
        };
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            $lines = explode("\n", $para);
            if (preg_match('/^\d+\.\s/', trim($lines[0] ?? ''))) {
                // Numbered list block — collect items; consecutive list blocks are merged
                $currentItem = '';
                foreach ($lines as $ln) {
                    $ln = trim($ln);
                    if (preg_match('/^\d+\.\s+(.+)$/', $ln, $lm)) {
                        if ($currentItem !== '') $listItems[] = $currentItem;
                        $currentItem = $lm[1];
                    } elseif ($ln !== '' && $currentItem !== '') {
                        $currentItem .= ' ' . $ln;
                    }
                }
                if ($currentItem !== '') $listItems[] = $currentItem;
            } else {
                $flushList(); // close any open list before regular paragraph
                $htmlParts[] = '<p>' . nl2br(implode("\n", array_map('trim', $lines))) . '</p>';
            }
        }
        $flushList();
        return implode('', $htmlParts) ?: nl2br($processed);
    }
}
