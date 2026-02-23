<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Lead Captured</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;padding:24px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <tr>
          <td style="background:#7b1fa2;padding:20px 30px;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">&#11088; New Lead Captured</h1>
            <p style="margin:6px 0 0 0;color:#e1bee7;font-size:13px;">{{ $organization->name }}</p>
          </td>
        </tr>

        {{-- Lead Info --}}
        <tr>
          <td style="padding:20px 30px 0 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8f9fa;border-radius:6px;padding:16px;">
              <tr><td colspan="2" style="padding-bottom:10px;"><strong style="font-size:13px;color:#555;text-transform:uppercase;letter-spacing:.5px;">Lead Details</strong></td></tr>
              <tr>
                <td style="width:110px;padding:4px 0;font-size:13px;color:#888;">Name</td>
                <td style="padding:4px 0;font-size:13px;color:#222;"><strong>{{ $lead->name ?? 'N/A' }}</strong></td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Email</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $lead->email ?? 'N/A' }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Phone</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $lead->phone ?? 'N/A' }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Priority</td>
                <td style="padding:4px 0;font-size:13px;">
                  @php $priority = strtolower($lead->priority ?? 'normal'); @endphp
                  <span style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;background:{{ $priority === 'high' ? '#fce4ec' : ($priority === 'critical' ? '#ffcdd2' : '#e8f5e9') }};color:{{ $priority === 'high' ? '#c62828' : ($priority === 'critical' ? '#b71c1c' : '#2e7d32') }};">
                    {{ ucfirst($priority) }}
                  </span>
                </td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Status</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ ucfirst($lead->status ?? 'new') }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Captured At</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ optional($lead->created_at)->format('M j, Y g:i A') ?? $lead->created_at }}</td>
              </tr>
            </table>
          </td>
        </tr>

        @if(!empty($intent))
        {{-- Intent --}}
        <tr>
          <td style="padding:16px 30px 0 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ede7f6;border-radius:6px;padding:12px 16px;">
              <tr>
                <td style="font-size:13px;color:#555;">
                  <strong>Detected Intent:</strong> {{ $intent['intent'] ?? 'N/A' }}
                  &nbsp;<span style="color:#888;font-size:12px;">(confidence: {{ $intent['confidence'] ?? '0' }})</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        @endif

        @if(!empty($message))
        {{-- Last Message --}}
        <tr>
          <td style="padding:16px 30px 0 30px;">
            <p style="margin:0 0 8px 0;font-size:13px;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Last Message</p>
            <div style="background:#f1f3f5;border-radius:6px;padding:12px 14px;font-size:14px;color:#333;line-height:1.6;">{!! nl2br(e($message)) !!}</div>
          </td>
        </tr>
        @endif

        {{-- Footer --}}
        <tr>
          <td style="padding:20px 30px 24px 30px;border-top:1px solid #eee;margin-top:20px;">
            <p style="margin:0;font-size:11px;color:#aaa;text-align:center;">Lead captured via AI Chat for {{ $organization->name }}. Log in to your dashboard to follow up.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
