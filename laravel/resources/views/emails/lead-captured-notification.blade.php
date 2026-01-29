<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Lead</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111;">
    <h2>New Lead Captured</h2>

    <p><strong>Organization:</strong> {{ $organization->name }}</p>
    <p><strong>Name:</strong> {{ $lead->name ?? 'N/A' }}</p>
    <p><strong>Email:</strong> {{ $lead->email ?? 'N/A' }}</p>
    <p><strong>Phone:</strong> {{ $lead->phone ?? 'N/A' }}</p>
    <p><strong>Priority:</strong> {{ ucfirst($lead->priority ?? 'normal') }}</p>
    <p><strong>Status:</strong> {{ ucfirst($lead->status ?? 'new') }}</p>

    @if(!empty($intent))
        <p><strong>Intent:</strong> {{ $intent['intent'] ?? 'N/A' }} ({{ $intent['confidence'] ?? '0' }})</p>
    @endif

    @if(!empty($message))
        <p><strong>Last Message:</strong> {{ $message }}</p>
    @endif

    <p><strong>Captured At:</strong> {{ $lead->created_at }}</p>
</body>
</html>
