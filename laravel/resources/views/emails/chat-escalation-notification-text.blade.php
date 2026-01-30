Chat Escalation

Organization: {{ $organization->name ?? 'N/A' }}

Visitor: {{ $conversation->visitor_name ?? 'Anonymous' }}
Email: {{ $conversation->visitor_email ?? 'Not provided' }}
Phone: {{ $conversation->visitor_phone ?? 'Not provided' }}
Reason: {{ $reason ?? 'N/A' }}
Session ID: {{ $conversation->conversation_id ?? '' }}

@if(!empty($summary))
Conversation Summary:
{{ $summary }}
@endif

@if(!empty($reply_to))
Reply to this email or send context to: {{ $reply_to }}
@endif

Open Live Chats console: {{ $console_url }}
