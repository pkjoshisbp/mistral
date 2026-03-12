<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Chat Interaction</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f9;padding:24px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Header --}}
        <tr>
          <td style="background:#1a73e8;padding:20px 30px;">
            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">New Chat Interaction</h1>
            <p style="margin:6px 0 0 0;color:#c8deff;font-size:13px;">{{ $organization->name ?? 'N/A' }}</p>
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
                <td style="padding:4px 0;font-size:13px;color:#888;">Time</td>
                <td style="padding:4px 0;font-size:13px;color:#222;">{{ $conversation->created_at->format('M j, Y g:i:s A') }}</td>
              </tr>
              <tr>
                <td style="padding:4px 0;font-size:13px;color:#888;">Session</td>
                <td style="padding:4px 0;font-size:11px;color:#666;font-family:monospace;">{{ $conversation->conversation_id ?? '' }}</td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Conversation --}}
        <tr>
          <td style="padding:20px 30px 0 30px;">
            @php
            /**
             * Format a plain-text AI/user response for HTML email:
             * - Normalises inline numbered lists to separate items
             * - Renders numbered lists as <ol><li> with inline email styles
             * - Renders bold markdown (**text**) as <strong>
             * - Wraps normal paragraphs in <p> tags
             */
            function emailFormatText(string $raw): string {
                $text = strip_tags($raw);
                // Numbered list normalisation
                $text = preg_replace('/([^\n])\s*(\d+)\.\s*\n/u', "$1\n\n$2. ", $text);
                $text = preg_replace('/\n(\d+\.\s)/u', "\n\n$1", $text);
                $text = preg_replace('/([.!?:,)])\s+(\d+\.\s+)(?=\D)/u', "$1\n\n$2", $text);
                // Bold markdown
                $boldTokens = [];
                $text = preg_replace_callback('/\*\*([^*\n]+?)\*\*/u', function($m) use (&$boldTokens) {
                    $boldTokens[] = $m[1]; return '__BOLD_'.(count($boldTokens)-1).'__';
                }, $text) ?? $text;
                $text = e($text);
                foreach ($boldTokens as $i => $inner) {
                    $text = str_replace('__BOLD_'.$i.'__', '<strong>'.e($inner).'</strong>', $text);
                }
                $paragraphs = preg_split('/\n\n+/', $text);
                if ($paragraphs === false || count($paragraphs) <= 1) {
                    return '<p style="margin:0 0 6px 0;">'.nl2br($text).'</p>';
                }
                $html = ''; $listItems = [];
                $flushList = function() use (&$listItems, &$html) {
                    if (!empty($listItems)) {
                        $html .= '<ol style="margin:6px 0;padding-left:18px;">';
                        foreach ($listItems as $item) $html .= '<li style="margin-bottom:3px;">'.$item.'</li>';
                        $html .= '</ol>'; $listItems = [];
                    }
                };
                foreach ($paragraphs as $para) {
                    $para = trim($para); if ($para === '') continue;
                    $lines = explode("\n", $para);
                    if (preg_match('/^\d+\.\s/', trim($lines[0] ?? ''))) {
                        $cur = '';
                        foreach ($lines as $ln) {
                            $ln = trim($ln);
                            if (preg_match('/^\d+\.\s+(.+)$/', $ln, $m)) { if ($cur !== '') $listItems[] = $cur; $cur = $m[1]; }
                            elseif ($ln !== '' && $cur !== '') $cur .= ' '.$ln;
                        }
                        if ($cur !== '') $listItems[] = $cur;
                    } else { $flushList(); $html .= '<p style="margin:0 0 6px 0;">'.nl2br(implode("\n", array_map('trim', $lines))).'</p>'; }
                }
                $flushList();
                return $html ?: '<p style="margin:0 0 6px 0;">'.nl2br($text).'</p>';
            }
            @endphp

            <p style="margin:0 0 10px 0;font-size:13px;color:#555;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Conversation</p>

            {{-- User message --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
              <tr>
                <td style="width:32px;vertical-align:top;padding-top:2px;">
                  <div style="width:28px;height:28px;background:#e0e0e0;border-radius:50%;text-align:center;line-height:28px;font-size:12px;font-weight:700;color:#555;">V</div>
                </td>
                <td style="padding-left:10px;">
                  <div style="font-size:11px;color:#888;margin-bottom:4px;">
                    Visitor
                    @if(!empty($user_sent_at))
                      <span style="margin-left:8px;color:#bbb;">{{ \Carbon\Carbon::parse($user_sent_at)->format('M j, Y g:i:s A') }}</span>
                    @endif
                  </div>
                  <div style="background:#f1f3f5;border-radius:0 8px 8px 8px;padding:10px 14px;font-size:14px;color:#333;line-height:1.6;">{!! nl2br(e($user_message)) !!}</div>
                </td>
              </tr>
            </table>

            {{-- AI response --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:6px;">
              <tr>
                <td style="width:32px;vertical-align:top;padding-top:2px;">
                  <div style="width:28px;height:28px;background:#1a73e8;border-radius:50%;text-align:center;line-height:28px;font-size:12px;font-weight:700;color:#fff;">AI</div>
                </td>
                <td style="padding-left:10px;">
                  <div style="font-size:11px;color:#888;margin-bottom:4px;">
                    AI Assistant
                    @if(!empty($ai_sent_at))
                      <span style="margin-left:8px;color:#bbb;">{{ \Carbon\Carbon::parse($ai_sent_at)->format('M j, Y g:i:s A') }}</span>
                    @endif
                  </div>
                  <div style="background:#e8f0fe;border-radius:0 8px 8px 8px;padding:10px 14px;font-size:14px;color:#333;line-height:1.6;">{!! emailFormatText($ai_response) !!}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="padding:20px 30px 24px 30px;border-top:1px solid #eee;margin-top:20px;">
            <p style="margin:0;font-size:11px;color:#aaa;text-align:center;">Chat notifications are enabled for {{ $organization->name ?? 'your organization' }}. Manage settings in your admin panel.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
