New Chat Interaction

Organization: {{ $organization->name ?? 'N/A' }}

Visitor: {{ $user_info['name'] ?? 'Anonymous' }}
Email: {{ $user_info['email'] ?? 'Not provided' }}
Phone: {{ $user_info['phone'] ?? 'Not provided' }}
Location: {{ $location_info['country'] ?? '' }} {{ $location_info['region'] ?? '' }} {{ $location_info['location'] ?? '' }}
Time: {{ $conversation->created_at->format('M j, Y g:i:s A') }}
Session ID: {{ $conversation->conversation_id ?? '' }}

User{{ !empty($user_sent_at) ? ' [' . \Carbon\Carbon::parse($user_sent_at)->format('M j, Y g:i:s A') . ']' : '' }}:
{{ $user_message }}

AI Assistant{{ !empty($ai_sent_at) ? ' [' . \Carbon\Carbon::parse($ai_sent_at)->format('M j, Y g:i:s A') . ']' : '' }}:
{{ $ai_response }}

This email was sent because chat notifications are enabled for this organization.
