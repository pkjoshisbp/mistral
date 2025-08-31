<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'organization_id',
        'user_id',
        'visitor_id',
        'visitor_email',
        'visitor_name',
        'status',
        'title',
        'summary',
        'metadata',
        'last_activity_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->latest('sent_at');
    }

    public function updateLastActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function generateTitle()
    {
        $firstMessage = $this->messages()->where('sender_type', 'user')->first();
        if ($firstMessage) {
            $title = substr($firstMessage->message, 0, 50);
            if (strlen($firstMessage->message) > 50) {
                $title .= '...';
            }
            $this->update(['title' => $title]);
        }
    }

    public function getDisplayName()
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->visitor_name ?: 'Anonymous User';
    }

    public function getContactInfo()
    {
        if ($this->user) {
            return $this->user->email;
        }
        return $this->visitor_email ?: 'No email provided';
    }
}
