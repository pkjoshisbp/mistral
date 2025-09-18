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
        // Escape first
        $escaped = e($this->message);
        // Linkify http/https URLs
        $linked = preg_replace_callback('/https?:\/\/[^\s<]+/i', function ($matches) {
            $full = $matches[0];
            $trail = '';
            // Move trailing punctuation outside link
            while (preg_match('/[\.,!?)]$/', $full)) {
                $trail = substr($full, -1) . $trail;
                $full = substr($full, 0, -1);
            }
            $display = strlen($full) > 80 ? substr($full,0,77).'...' : $full;
            return '<a href="'.e($full).'" target="_blank" rel="nofollow noopener noreferrer">'.$display.'</a>'.$trail;
        }, $escaped);
        // Preserve newlines
        return nl2br($linked);
    }
}
