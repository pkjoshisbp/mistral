<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Chat Session Export</title>
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
<h1>Chat Session Export</h1>
<div class="meta">
Organization: {{ $session->organization->name ?? 'N/A' }}<br>
Started: {{ $session->created_at->format('Y-m-d H:i:s') }}<br>
Duration: {{ $duration }}<br>
Total Messages: {{ $session->messages->count() }}
</div>
<hr>
@foreach($session->messages as $m)
<div class="message">
    <span class="time">[{{ $m->created_at->format('H:i:s') }}]</span>
    <span class="sender-{{ $m->sender }}">{{ ucfirst($m->sender) }}:</span>
    <span class="content">{{ $m->content }}</span>
</div>
@endforeach
</body></html>
