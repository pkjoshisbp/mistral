<div style="font-family: Arial, sans-serif; color: #222;">
    <h2 style="margin: 0 0 8px 0;">Chat Escalation</h2>
    <p style="margin: 0 0 12px 0; color: #666;">Organization: <strong>{{ $organization->name ?? 'N/A' }}</strong></p>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 12px 0;">

    <p style="margin: 0 0 6px 0;"><strong>Visitor:</strong> {{ $conversation->visitor_name ?? 'Anonymous' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Email:</strong> {{ $conversation->visitor_email ?? 'Not provided' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Phone:</strong> {{ $conversation->visitor_phone ?? 'Not provided' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Reason:</strong> {{ $reason ?? 'N/A' }}</p>
    <p style="margin: 0 0 6px 0;"><strong>Session ID:</strong> {{ $conversation->conversation_id ?? '' }}</p>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 12px 0;">

    @if(!empty($summary))
        <p style="margin: 0 0 6px 0;"><strong>Conversation Summary:</strong></p>
        <div style="padding: 10px; background: #f7f7f7; border-radius: 6px; white-space: pre-wrap;">{{ $summary }}</div>
    @endif

    <p style="margin: 12px 0 0 0; font-size: 12px; color: #888;">
        Open the Live Chats console to respond.
        <a href="{{ $console_url }}" target="_blank" rel="noopener noreferrer">{{ $console_url }}</a>
    </p>
</div>
