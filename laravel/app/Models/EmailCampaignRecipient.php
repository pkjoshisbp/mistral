<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_campaign_id',
        'organization_id',
        'recipient_email',
        'tracking_token',
        'message_id',
        'provider',
        'delivery_status',
        'delivered_at',
        'opened_at',
        'last_opened_at',
        'open_count',
        'resend_count',
        'last_sent_at',
        'next_resend_at',
        'last_event',
        'last_event_at',
        'variables',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'variables' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'last_event_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'next_resend_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}