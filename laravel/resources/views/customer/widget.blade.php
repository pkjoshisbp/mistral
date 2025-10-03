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
                                            @php $custOrg = auth()->user()->organizations->first(); $pos = $custOrg->settings['widget_position'] ?? 'bottom-right'; @endphp
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
                                    <button type="submit" class="btn btn-success">Save Settings</button>
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
                                        <i class="fas fa-comments fa-4x text-primary mb-3"></i>
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

// Handle form submit with fetch to show toast without reload
document.getElementById('widgetSettingsForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const payload = {
        primaryColor: document.getElementById('primaryColor').value,
        chatPosition: document.getElementById('chatPosition').value,
        offsetX: parseInt(document.getElementById('offsetX').value || '20', 10),
        offsetY: parseInt(document.getElementById('offsetY').value || '20', 10),
        welcomeMessage: document.getElementById('welcomeMessage').value,
        assistantDisplayName: document.getElementById('assistantDisplayName').value,
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
