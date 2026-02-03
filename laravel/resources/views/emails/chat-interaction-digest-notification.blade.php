<div style="font-family: Arial, sans-serif; color: #222;">
    <h2 style="margin: 0 0 8px 0;">Chat Digest</h2>
    <p style="margin: 0 0 12px 0; color: #666;">Organization: <strong>{{ $organization->name ?? 'N/A' }}</strong></p>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 12px 0;">

    <p style="margin: 0 0 6px 0;"><strong>Visitor:</strong> {{ $user_info['name'] ?? 'Anonymous' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Email:</strong> {{ $user_info['email'] ?? 'Not provided' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Phone:</strong> {{ $user_info['phone'] ?? 'Not provided' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Location:</strong> {{ $location_info['country'] ?? '' }} {{ $location_info['region'] ?? '' }} {{ $location_info['location'] ?? '' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Session ID:</strong> {{ $conversation->conversation_id ?? '' }}</p>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 12px 0;">

    <p style="margin: 0 0 10px 0;"><strong>Messages ({{ $message_count ?? 0 }})</strong></p>

    @foreach ($messages as $message)
        <div style="padding: 10px; background: {{ $message->isFromUser() ? '#f7f7f7' : '#eef6ff' }}; border-radius: 6px; margin-bottom: 10px;">
            <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                {{ $message->getSenderDisplayName() }} • {{ optional($message->sent_at)->format('M j, Y g:i A') }}
            </div>
            <div>{{ $message->message }}</div>
        </div>
    @endforeach

    <hr style="border: 0; border-top: 1px solid #eee; margin: 12px 0;">

    <p style="margin: 0; color: #888; font-size: 12px;">This digest was sent because chat notifications are enabled for this organization.</p>
</div>
