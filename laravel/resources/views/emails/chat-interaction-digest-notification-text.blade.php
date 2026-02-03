Chat Digest

Organization: {{ $organization->name ?? 'N/A' }}

Visitor: {{ $user_info['name'] ?? 'Anonymous' }}
Email: {{ $user_info['email'] ?? 'Not provided' }}
Phone: {{ $user_info['phone'] ?? 'Not provided' }}
Location: {{ $location_info['country'] ?? '' }} {{ $location_info['region'] ?? '' }} {{ $location_info['location'] ?? '' }}
Session ID: {{ $conversation->conversation_id ?? '' }}

Messages ({{ $message_count ?? 0 }}):
@foreach ($messages as $message)
- {{ $message->getSenderDisplayName() }} ({{ optional($message->sent_at)->format('M j, Y g:i A') }}):
  {{ $message->message }}
@endforeach

This digest was sent because chat notifications are enabled for this organization.
