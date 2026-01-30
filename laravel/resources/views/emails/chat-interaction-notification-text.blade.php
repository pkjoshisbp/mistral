New Chat Interaction

Organization: {{ $organization->name ?? 'N/A' }}

Visitor: {{ $user_info['name'] ?? 'Anonymous' }}
Email: {{ $user_info['email'] ?? 'Not provided' }}
Phone: {{ $user_info['phone'] ?? 'Not provided' }}
Location: {{ $location_info['country'] ?? '' }} {{ $location_info['region'] ?? '' }} {{ $location_info['location'] ?? '' }}
Session ID: {{ $conversation->conversation_id ?? '' }}

User:
{{ $user_message }}

AI Response:
{{ $ai_response }}

This email was sent because chat notifications are enabled for this organization.
