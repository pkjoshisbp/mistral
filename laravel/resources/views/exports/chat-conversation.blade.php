<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Chat Conversation Export</title>
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; }
h1 { font-size:18px; margin-bottom:4px; }
.meta { margin-bottom:10px; }
.message { margin-bottom:6px; }
.sender-user { font-weight:bold; color:#1d4ed8; }
.sender-bot { font-weight:bold; color:#065f46; }
.time { color:#6b7280; font-size:11px; }
.content { margin-left:8px; }
</style></head><body>
<h1>Chat Conversation Export</h1>
<div class="meta">
Organization: {{ $conversation->organization->name ?? 'N/A' }}<br>
Started: {{ $conversation->created_at->format('Y-m-d H:i:s') }}<br>
Duration: {{ $duration }}<br>
Total Messages: {{ $conversation->messages->count() }}
</div>
<hr>
@foreach($conversation->messages as $message)
<div class="message">
    <span class="time">[{{ $message->created_at->format('H:i:s') }}]</span>
    <span class="sender-{{ $message->sender ?? 'system' }}">{{ ucfirst($message->sender ?? 'System') }}:</span>
    <span class="content">{{ $message->content ?? '' }}</span>
</div>
@endforeach
</body></html>