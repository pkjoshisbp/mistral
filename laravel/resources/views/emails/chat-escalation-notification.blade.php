<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat Escalation</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;padding:24px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <tr>
          <td style="background:#d93025;padding:20px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td>
                  <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">&#9888; Chat Escalation</h1>
                  <p style="margin:6px 0 0 0;color:#ffd0cc;font-size:13px;">{{ $organization->name ?? 'N/A' }} &mdash; Human agent required</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Visitor Info --}}
        <tr>
          <td style="padding:20px 30px 0 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8f9fa;border-radius:6px;padding:16px;">
              <tr><td colspan="2" style="padding-bottom:10px;"><strong style="font-size:13px;color:#555;text-transform:uppercase;letter-spacing:.5px;">Visitor Details</strong></td></tr>
              <tr>
                <td style="width:110px;padding:4px 0;font-size:13px;color:#888;">Visitor</td>
                <td style="padding:4px 0;font-size:13px;color:#222;"><strong>{{ $conversation->visitor_name ?? 'Anonymous' }}</strong></td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Email</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $conversation->visitor_email ?? 'Not provided' }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Phone</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $conversation->visitor_phone ?? 'Not provided' }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Reason</td>
                <td style="padding:4px 0;font-size:13px;color:#c00;"><strong>{{ $reason ?? 'N/A' }}</strong></td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Session</td>
                <td style="padding:4px 0;font-size:11px;color:#666;font-family:monospace;">{{ $conversation->conversation_id ?? '' }}</td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Conversation Summary --}}
        @if(!empty($summary))
        <tr>
          <td style="padding:20px 30px 0 30px;">
            <p style="margin:0 0 10px 0;font-size:13px;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Conversation Summary</p>
            <div style="background:#fff8e1;border-left:4px solid #f9a825;border-radius:0 6px 6px 0;padding:12px 16px;font-size:14px;color:#333;line-height:1.7;white-space:pre-wrap;">{!! nl2br(e($summary)) !!}</div>
          </td>
        </tr>
        @endif

        {{-- Action Buttons --}}
        <tr>
          <td style="padding:20px 30px 0 30px;">
            <p style="margin:0 0 12px 0;font-size:13px;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Actions</p>
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                @if(!empty($magic_link))
                <td style="padding-right:10px;">
                  <a href="{{ $magic_link }}" target="_blank" rel="noopener noreferrer"
                     style="display:inline-block;background:#d93025;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-size:13px;font-weight:600;">Open Escalation Console</a>
                  <div style="font-size:11px;color:#999;margin-top:4px;">Valid for {{ $magic_link_ttl_minutes ?? 30 }} minutes</div>
                </td>
                @endif
                <td>
                  <a href="{{ $console_url }}" target="_blank" rel="noopener noreferrer"
                     style="display:inline-block;background:#1a73e8;color:#fff;text-decoration:none;padding:10px 20px;border-radius:6px;font-size:13px;font-weight:600;">Live Chat Console</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="padding:20px 30px 24px 30px;">
            @if(!empty($reply_to))
            <p style="margin:0 0 6px 0;font-size:12px;color:#888;">Reply to this email or contact visitor at: <a href="mailto:{{ $reply_to }}" style="color:#1a73e8;">{{ $reply_to }}</a></p>
            @endif
            <p style="margin:0;font-size:11px;color:#aaa;border-top:1px solid #eee;padding-top:12px;">This escalation was triggered for {{ $organization->name ?? 'your organization' }}. Open the console above to respond to the visitor.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
