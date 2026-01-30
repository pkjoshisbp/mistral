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
        'visitor_phone',
        'visitor_country',
        'visitor_region',
        'visitor_location',
        'status',
        'agent_status',
        'assigned_agent_id',
        'escalated_at',
        'agent_assigned_at',
        'agent_last_active_at',
        'closed_at',
        'title',
        'summary',
        'metadata',
        'last_activity_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_activity_at' => 'datetime',
        'escalated_at' => 'datetime',
        'agent_assigned_at' => 'datetime',
        'agent_last_active_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        // Always return messages in chronological order by sent_at (fallback to id)
        return $this->hasMany(ChatMessage::class, 'conversation_id')
            ->orderBy('sent_at', 'asc')
            ->orderBy('id', 'asc');
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
