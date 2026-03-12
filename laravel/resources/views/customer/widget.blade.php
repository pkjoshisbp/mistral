@extends('layouts.customer')

@section('title', 'Widget Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Widget Configuration</h4>
                    <p class="text-muted mb-0">Customize and embed your AI chat widget</p>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-4">
                                <h5>Widget Code</h5>
                                <p class="text-muted">Copy and paste this code into your website to add the AI chat widget:</p>
                                <div class="bg-light p-3 rounded">
                                    <code>
&lt;script&gt;<br>
&nbsp;&nbsp;(function() {<br>
&nbsp;&nbsp;&nbsp;&nbsp;const script = document.createElement('script');<br>
&nbsp;&nbsp;&nbsp;&nbsp;script.src = 'https://ai-chat.support/widget/{{ auth()->user()->primaryOrganization()?->id ?? auth()->user()->organization_id ?? 3 }}/script.js';<br>
&nbsp;&nbsp;&nbsp;&nbsp;script.async = true;<br>
&nbsp;&nbsp;&nbsp;&nbsp;document.head.appendChild(script);<br>
&nbsp;&nbsp;})();<br>
&lt;/script&gt;
                                    </code>
                                </div>
                                <button class="btn btn-primary mt-2" onclick="copyWidgetCode()">
                                    <i class="fas fa-copy me-2"></i>Copy Code
                                </button>
                            </div>
                            
                            <div class="mb-4">
                                <h5>Widget Customization</h5>
                                <form id="widgetSettingsForm" method="POST" action="{{ route('customer.widget.settings.save') }}">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Widget Position</label>
                                            @php $custOrg = auth()->user()->primaryOrganization() ?? auth()->user()->organizations->first(); $pos = $custOrg->settings['widget_position'] ?? 'bottom-right'; @endphp
                                            <input type="hidden" id="organizationId" name="organizationId" value="{{ $custOrg->id }}">
                                            <select class="form-select" id="chatPosition" name="chatPosition">
                                                <option value="bottom-right" {{ $pos==='bottom-right' ? 'selected' : '' }}>Bottom Right</option>
                                                <option value="bottom-left" {{ $pos==='bottom-left' ? 'selected' : '' }}>Bottom Left</option>
                                                <option value="top-right" {{ $pos==='top-right' ? 'selected' : '' }}>Top Right</option>
                                                <option value="top-left" {{ $pos==='top-left' ? 'selected' : '' }}>Top Left</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Primary Color</label>
                                            <input type="color" id="primaryColor" name="primaryColor" class="form-control form-control-color" value="{{ $custOrg->settings['primary_color'] ?? '#007bff' }}">
                                        </div>
                                    </div>
                                    @php
                                        $widgetButtonBgType = $custOrg->settings['widget_button_bg_type'] ?? 'gradient';
                                        $widgetButtonSolidColor = $custOrg->settings['widget_button_solid_color'] ?? ($custOrg->settings['primary_color'] ?? '#007bff');
                                        $widgetButtonGradientStart = $custOrg->settings['widget_button_gradient_start'] ?? '#667eea';
                                        $widgetButtonGradientEnd = $custOrg->settings['widget_button_gradient_end'] ?? '#764ba2';
                                        $widgetButtonGradientAngle = (int) ($custOrg->settings['widget_button_gradient_angle'] ?? 135);
                                        $previewLauncherBackground = $widgetButtonBgType === 'solid'
                                            ? $widgetButtonSolidColor
                                            : 'linear-gradient(' . $widgetButtonGradientAngle . 'deg, ' . $widgetButtonGradientStart . ', ' . $widgetButtonGradientEnd . ')';
                                    @endphp
                                    <input type="hidden" id="widgetIconColor" name="widgetIconColor" value="#ffffff">
                                    @php
                                        $assistantBubbleBgColor = $custOrg->settings['widget_bot_bubble_bg_color'] ?? '#f4f8f6';
                                        $assistantBubbleTextColor = $custOrg->settings['widget_bot_bubble_text_color'] ?? '#000000';
                                    @endphp
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Launcher Background</label>
                                            <select class="form-select" id="widgetButtonBgType" name="widgetButtonBgType">
                                                <option value="gradient" {{ $widgetButtonBgType === 'gradient' ? 'selected' : '' }}>Gradient</option>
                                                <option value="solid" {{ $widgetButtonBgType === 'solid' ? 'selected' : '' }}>Solid</option>
                                            </select>
                                            <small class="text-muted">Controls bubble background around the icon.</small>
                                        </div>
                                        <div class="col-md-4 mb-3" id="widgetSolidColorWrap">
                                            <label class="form-label">Solid Background Color</label>
                                            <input type="color" id="widgetButtonSolidColor" name="widgetButtonSolidColor" class="form-control form-control-color" value="{{ $widgetButtonSolidColor }}">
                                        </div>
                                        <div class="col-md-4 mb-3" id="widgetGradientStartWrap">
                                            <label class="form-label">Gradient Start</label>
                                            <input type="color" id="widgetButtonGradientStart" name="widgetButtonGradientStart" class="form-control form-control-color" value="{{ $widgetButtonGradientStart }}">
                                        </div>
                                        <div class="col-md-4 mb-3" id="widgetGradientEndWrap">
                                            <label class="form-label">Gradient End</label>
                                            <input type="color" id="widgetButtonGradientEnd" name="widgetButtonGradientEnd" class="form-control form-control-color" value="{{ $widgetButtonGradientEnd }}">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Assistant Bubble Background</label>
                                            <input type="color" id="assistantBubbleBgColor" name="assistantBubbleBgColor" class="form-control form-control-color" value="{{ $assistantBubbleBgColor }}">
                                            <small class="text-muted">Default: #F4F8F6 (light grey / silver)</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Assistant Bubble Text Color</label>
                                            <input type="color" id="assistantBubbleTextColor" name="assistantBubbleTextColor" class="form-control form-control-color" value="{{ $assistantBubbleTextColor }}">
                                        </div>
                                    </div>
                                    <div class="row" id="widgetGradientAngleRow">
                                        <div class="col-md-4 mb-3" id="widgetGradientAngleWrap">
                                            <label class="form-label">Gradient Angle (deg)</label>
                                            <input type="number" id="widgetButtonGradientAngle" name="widgetButtonGradientAngle" class="form-control" min="0" max="360" step="1" value="{{ $widgetButtonGradientAngle }}">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Horizontal Offset (px)</label>
                                            <input type="number" id="offsetX" name="offsetX" class="form-control" min="0" step="1" value="{{ (int)($custOrg->settings['widget_offset_x'] ?? 20) }}" placeholder="e.g., 20">
                                            <small class="text-muted">Distance from left/right edge depending on position</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vertical Offset (px)</label>
                                            <input type="number" id="offsetY" name="offsetY" class="form-control" min="0" step="1" value="{{ (int)($custOrg->settings['widget_offset_y'] ?? 20) }}" placeholder="e.g., 20">
                                            <small class="text-muted">Distance from top/bottom edge depending on position</small>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Welcome Message</label>
                                        <input type="text" id="welcomeMessage" name="welcomeMessage" class="form-control" value="{{ $custOrg->settings['welcome_message'] ?? 'Hello! How can I help you today?' }}" placeholder="Enter welcome message">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Assistant Display Name</label>
                                        <input type="text" id="assistantDisplayName" name="assistantDisplayName" class="form-control" value="{{ $custOrg->settings['assistant_display_name'] ?? '' }}" placeholder="e.g., Ava, Support Bot, Acme Assistant">
                                        <small class="text-muted">This name will appear next to AI messages in chats.</small>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="requireContactForGuests" name="requireContactForGuests" {{ !empty($custOrg->settings['require_contact_for_guests']) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="requireContactForGuests">
                                                Contact info required before chat
                                            </label>
                                        </div>
                                        <small class="text-muted">If enabled, visitors must provide name, email, and phone before chat starts. Skip button will be hidden.</small>
                                    </div>
                                    @php
                                        $contactFieldsLines = '';
                                        $configuredContactFields = $custOrg->settings['widget_contact_fields'] ?? [];
                                        if (is_array($configuredContactFields)) {
                                            $contactFieldsLines = implode("\n", array_map(function ($f) {
                                                $key = (string) ($f['key'] ?? '');
                                                $label = (string) ($f['label'] ?? '');
                                                $type = (string) ($f['type'] ?? 'text');
                                                $required = !empty($f['required']) ? 'true' : 'false';
                                                if ($key === '') {
                                                    return '';
                                                }
                                                return $key . '|' . $label . '|' . $type . '|' . $required;
                                            }, $configuredContactFields));
                                        }
                                    @endphp
                                    <div class="mb-3">
                                        <label class="form-label">Additional Lead Fields (optional)</label>
                                        <textarea id="widgetContactFields" name="widgetContactFields" class="form-control" rows="4" placeholder="location|Location|location|true&#10;city|City|text|false">{{ $contactFieldsLines }}</textarea>
                                        <small class="text-muted">One field per line in format: <strong>key|Label|type|required</strong>. Types: text, email, phone, number, location.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Allowed Widget Domains (optional, comma/newline separated)</label>
                                        <textarea id="widgetAllowedDomains" name="widgetAllowedDomains" class="form-control" rows="3" placeholder="example.com&#10;www.example.com">{{ is_array($custOrg->settings['widget_allowed_domains'] ?? null) ? implode("\n", $custOrg->settings['widget_allowed_domains']) : ($custOrg->settings['widget_allowed_domains'] ?? '') }}</textarea>
                                        <small class="text-muted">If set, widget chat requests will only be accepted from these domains (including subdomains).</small>
                                    </div>
                                    @php
                                        $queryTranslationMap = $custOrg->settings['query_translation_map'] ?? '';
                                        if (is_array($queryTranslationMap)) {
                                            $queryTranslationMap = implode("\n", array_map(function ($to, $from) {
                                                if (is_int($from)) {
                                                    return (string) $to;
                                                }
                                                return trim((string) $from) . ' => ' . trim((string) $to);
                                            }, $queryTranslationMap, array_keys($queryTranslationMap)));
                                        }
                                    @endphp
                                    <div class="mb-3">
                                        <label class="form-label">Query Translation Map (optional)</label>
                                        <textarea id="queryTranslationMap" name="queryTranslationMap" class="form-control" rows="6" placeholder="mehr infos = more information&#10;prix = price&#10;servicio = service">{{ $queryTranslationMap }}</textarea>
                                        <small class="text-muted">Use one mapping per line in format <strong>source = target</strong> (or <strong>source =&gt; target</strong>). Add commonly used terms from any language (for example Indic, German, French, Spanish, Tamil, Telugu) to improve multilingual FAQ matching.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Custom Widget CSS (optional)</label>
                                        <textarea id="widgetCustomCss" name="widgetCustomCss" class="form-control" rows="6" placeholder="/* Example */&#10;.ai-chat-window {&#10;  max-width: 460px !important;&#10;}">{{ $custOrg->settings['widget_custom_css'] ?? '' }}</textarea>
                                        <small class="text-muted">Applies only to this organization's widget.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Custom Widget JS (optional)</label>
                                        <textarea id="widgetCustomJs" name="widgetCustomJs" class="form-control" rows="6" placeholder="// Example&#10;console.log('Widget ready', config.orgId);">{{ $custOrg->settings['widget_custom_js'] ?? '' }}</textarea>
                                        <small class="text-muted">Executes in widget context for this organization only.</small>
                                    </div>
                                    <button type="submit" class="btn btn-success">Save Settings</button>
                                    <small id="widgetSavedModeInfo" class="ms-3 text-muted">Saved mode: {{ ucfirst($widgetButtonBgType) }}</small>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Widget Preview</h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center">
                                        <i class="fas fa-comments fa-4x mb-3" style="color: #ffffff; background: {{ $previewLauncherBackground }}; border-radius: 50%; padding: 16px;"></i>
                                        <h6>AI Chat Widget</h6>
                                        <p class="text-muted small">Your widget will appear on your website like this, positioned in the bottom corner for easy customer access.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-3 border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Quick Stats</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total Conversations:</span>
                                        <strong>{{ \App\Models\ChatConversation::where('organization_id', auth()->user()->primaryOrganization()?->id ?? auth()->user()->organization_id ?? 3)->count() }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Today's Chats:</span>
                                        <strong>{{ \App\Models\ChatConversation::where('organization_id', auth()->user()->primaryOrganization()?->id ?? auth()->user()->organization_id ?? 3)->whereDate('created_at', today())->count() }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Response Rate:</span>
                                        <strong class="text-success">98%</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyWidgetCode() {
    const organizationId = {{ auth()->user()->primaryOrganization()?->id ?? auth()->user()->organization_id ?? 3 }};
    const code = `<script>
(function() {
    const script = document.createElement('script');
    script.src = 'https://ai-chat.support/widget/${organizationId}/script.js';
    script.async = true;
    document.head.appendChild(script);
})();
<\/script>`;
    
    navigator.clipboard.writeText(code).then(function() {
        alert('Widget code copied to clipboard!');
    }).catch(function(err) {
        console.error('Failed to copy: ', err);
        // Fallback for older browsers
        const textArea = document.createElement("textarea");
        textArea.value = code;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Widget code copied to clipboard!');
    });
}

function toggleGradientInputs() {
    const bgType = (document.getElementById('widgetButtonBgType')?.value || 'gradient').toLowerCase();
    const showGradient = bgType === 'gradient';
    const showSolid = bgType === 'solid';

    const solidWrap = document.getElementById('widgetSolidColorWrap');
    const startWrap = document.getElementById('widgetGradientStartWrap');
    const endWrap = document.getElementById('widgetGradientEndWrap');
    const angleRow = document.getElementById('widgetGradientAngleRow');

    if (solidWrap) solidWrap.style.display = showSolid ? '' : 'none';
    if (startWrap) startWrap.style.display = showGradient ? '' : 'none';
    if (endWrap) endWrap.style.display = showGradient ? '' : 'none';
    if (angleRow) angleRow.style.display = showGradient ? '' : 'none';

    const solidInput = document.getElementById('widgetButtonSolidColor');
    const startInput = document.getElementById('widgetButtonGradientStart');
    const endInput = document.getElementById('widgetButtonGradientEnd');
    const angleInput = document.getElementById('widgetButtonGradientAngle');
    if (solidInput) solidInput.disabled = !showSolid;
    if (startInput) startInput.disabled = !showGradient;
    if (endInput) endInput.disabled = !showGradient;
    if (angleInput) angleInput.disabled = !showGradient;
}

document.getElementById('widgetButtonBgType')?.addEventListener('change', toggleGradientInputs);
toggleGradientInputs();

// Handle form submit with fetch to show toast without reload
document.getElementById('widgetSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const payload = {
        organizationId: parseInt(document.getElementById('organizationId').value || '0', 10),
        primaryColor: document.getElementById('primaryColor').value,
        widgetIconColor: document.getElementById('widgetIconColor').value,
        assistantBubbleBgColor: document.getElementById('assistantBubbleBgColor').value,
        assistantBubbleTextColor: document.getElementById('assistantBubbleTextColor').value,
        widgetButtonBgType: document.getElementById('widgetButtonBgType').value,
        widgetButtonSolidColor: document.getElementById('widgetButtonSolidColor').value,
        widgetButtonGradientStart: document.getElementById('widgetButtonGradientStart').value,
        widgetButtonGradientEnd: document.getElementById('widgetButtonGradientEnd').value,
        widgetButtonGradientAngle: parseInt(document.getElementById('widgetButtonGradientAngle').value || '135', 10),
        chatPosition: document.getElementById('chatPosition').value,
        offsetX: parseInt(document.getElementById('offsetX').value || '20', 10),
        offsetY: parseInt(document.getElementById('offsetY').value || '20', 10),
        welcomeMessage: document.getElementById('welcomeMessage').value,
        assistantDisplayName: document.getElementById('assistantDisplayName').value,
        requireContactForGuests: document.getElementById('requireContactForGuests').checked,
        widgetContactFields: document.getElementById('widgetContactFields').value,
        widgetAllowedDomains: document.getElementById('widgetAllowedDomains').value,
        queryTranslationMap: document.getElementById('queryTranslationMap').value,
        widgetCustomCss: document.getElementById('widgetCustomCss').value,
        widgetCustomJs: document.getElementById('widgetCustomJs').value,
        _token: form.querySelector('input[name="_token"]').value
    };
    try {
        const res = await fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': payload._token, 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) throw new Error('Failed to save settings');
        const data = await res.json();
        if (data.success) {
            if (data.settings?.widgetIconColor) {
                document.getElementById('widgetIconColor').value = data.settings.widgetIconColor;
            }
            if (data.settings?.primaryColor) {
                document.getElementById('primaryColor').value = data.settings.primaryColor;
            }
            if (data.settings?.assistantBubbleBgColor) {
                document.getElementById('assistantBubbleBgColor').value = data.settings.assistantBubbleBgColor;
            }
            if (data.settings?.assistantBubbleTextColor) {
                document.getElementById('assistantBubbleTextColor').value = data.settings.assistantBubbleTextColor;
            }
            if (data.settings?.widgetButtonBgType) {
                document.getElementById('widgetButtonBgType').value = data.settings.widgetButtonBgType;
                const modeInfo = document.getElementById('widgetSavedModeInfo');
                if (modeInfo) {
                    const mode = data.settings.widgetButtonBgType;
                    modeInfo.textContent = `Saved mode: ${mode.charAt(0).toUpperCase()}${mode.slice(1)}`;
                }
            }
            if (data.settings?.widgetButtonSolidColor) {
                document.getElementById('widgetButtonSolidColor').value = data.settings.widgetButtonSolidColor;
            }
            toggleGradientInputs();
            alert('Widget settings saved successfully!');
        } else {
            alert('Could not save settings.');
        }
    } catch (err) {
        console.error(err);
        alert('Error saving widget settings.');
    }
});
</script>
@endsection
