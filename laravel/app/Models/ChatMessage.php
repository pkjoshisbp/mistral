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

        // Escape early to prevent XSS; we'll inject safe anchors later
        $escaped = e($text);

        // Placeholder emails to ensure they are NOT linked
        $emailPlaceholders = [];
        $escaped = preg_replace_callback('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', function($m) use (&$emailPlaceholders){
            $emailPlaceholders[] = $m[0];
            return '__EMAIL_'.(count($emailPlaceholders)-1).'__';
        }, $escaped) ?? $escaped;

        // Linkify http/https URLs with punctuation trimming
        $processed = preg_replace_callback('/https?:\/\/[^\s<]+/i', function ($matches) {
            // The input string is already HTML-escaped, so decode to get the raw URL before building the anchor
            $full = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $trail = '';
            while (preg_match('/[\.,!?)]$/', $full)) {
                $trail = substr($full, -1) . $trail;
                $full = substr($full, 0, -1);
            }
            $display = strlen($full) > 80 ? substr($full,0,77).'...' : $full;
            return '<a href="'.e($full).'" target="_blank" rel="nofollow noopener noreferrer">'.e($display).'</a>'.$trail;
        }, $escaped) ?? $escaped;

        // Linkify bare domains while avoiding emails: require non-@, non-word char or start before domain
        // Note: escape the `~` inside the character class because `~` is used as the regex delimiter.
        // Additionally, do not match when the preceding character is ':' or '/' to avoid linking inside 'https://...'
        $processed = preg_replace_callback("~(^|[^@\\w:/])((?:[a-zA-Z0-9-]+\\.)+[a-zA-Z]{2,})(/[\\w\\-\\._\\~:/?#\\[\\]@!$&'()*+,;=%]*)?~i", function($m){
            $pre = $m[1];
            $host = $m[2];
            $path = html_entity_decode($m[3] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = 'https://' . $host . $path;
            return e($pre).'<a href="'.e($url).'" target="_blank" rel="nofollow noopener noreferrer">'.e($host.$path).'</a>';
        }, $processed) ?? $processed;

        // Restore anchors present in the original message
        foreach ($anchorPlaceholders as $i => $html) {
            $processed = str_replace('__ANCHOR_'.$i.'__', $html, $processed);
        }

        // Restore email placeholders as plain text (no link)
        foreach ($emailPlaceholders as $i => $email) {
            $processed = str_replace('__EMAIL_'.$i.'__', e($email), $processed);
        }

        // Preserve newlines
        return nl2br($processed);
    }
}
