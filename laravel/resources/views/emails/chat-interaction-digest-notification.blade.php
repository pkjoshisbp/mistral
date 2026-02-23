<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat Digest</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;padding:24px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <tr>
          <td style="background:#0d7c3a;padding:20px 30px;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">Chat Session Digest</h1>
            <p style="margin:6px 0 0 0;color:#a5e8c0;font-size:13px;">{{ $organization->name ?? 'N/A' }} &mdash; {{ $message_count ?? 0 }} message(s)</p>
          </td>
        </tr>

        {{-- Visitor Info --}}
        <tr>
          <td style="padding:20px 30px 0 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8f9fa;border-radius:6px;padding:16px;">
              <tr><td colspan="2" style="padding-bottom:10px;"><strong style="font-size:13px;color:#555;text-transform:uppercase;letter-spacing:.5px;">Visitor Details</strong></td></tr>
              <tr>
                <td style="width:110px;padding:4px 0;font-size:13px;color:#888;">Visitor</td>
                <td style="padding:4px 0;font-size:13px;color:#222;"><strong>{{ $user_info['name'] ?? 'Anonymous' }}</strong></td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Email</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $user_info['email'] ?? 'Not provided' }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Phone</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $user_info['phone'] ?? 'Not provided' }}</td>
              </tr>
              @php
                  $locationParts = array_filter([
                      $location_info['city'] ?? '',
                      $location_info['region'] ?? '',
                      $location_info['country'] ?? '',
                  ]);
                  $locationStr = implode(', ', $locationParts) ?: ($location_info['location'] ?? 'N/A');
              @endphp
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Location</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $locationStr }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Session</td>
                <td style="padding:4px 0;font-size:11px;color:#666;font-family:monospace;">{{ $conversation->conversation_id ?? '' }}</td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Messages --}}
        <tr>
          <td style="padding:20px 30px 0 30px;">
            <p style="margin:0 0 12px 0;font-size:13px;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Conversation</p>

            @foreach ($messages as $message)
              @php $isUser = $message->isFromUser(); @endphp
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
                <tr>
                  <td style="width:32px;vertical-align:top;padding-top:2px;">
                    <div style="width:28px;height:28px;background:{{ $isUser ? '#e0e0e0' : '#1a73e8' }};border-radius:50%;text-align:center;line-height:28px;font-size:11px;font-weight:700;color:{{ $isUser ? '#555' : '#fff' }};">
                      {{ $isUser ? 'V' : 'AI' }}
                    </div>
                  </td>
                  <td style="padding-left:10px;">
                    <div style="font-size:11px;color:#999;margin-bottom:4px;">
                      {{ $message->getSenderDisplayName() }} &bull; {{ optional($message->sent_at)->format('M j, g:i A') }}
                    </div>
                    <div style="background:{{ $isUser ? '#f1f3f5' : '#e8f0fe' }};border-radius:0 8px 8px 8px;padding:10px 14px;font-size:14px;color:#333;line-height:1.6;">
                      {!! nl2br(e($message->message)) !!}
                    </div>
                  </td>
                </tr>
              </table>
            @endforeach
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="padding:20px 30px 24px 30px;border-top:1px solid #eee;">
            <p style="margin:0;font-size:11px;color:#aaa;text-align:center;">Chat digest sent for {{ $organization->name ?? 'your organization' }}. Manage notification settings in your admin panel.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
