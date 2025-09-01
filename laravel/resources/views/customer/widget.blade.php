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
&nbsp;&nbsp;&nbsp;&nbsp;script.src = 'https://ai-chat.support/widget/{{ auth()->user()->organization_id ?? 3 }}/script.js';<br>
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
                                <form>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Widget Position</label>
                                            <select class="form-select">
                                                <option value="bottom-right">Bottom Right</option>
                                                <option value="bottom-left">Bottom Left</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Primary Color</label>
                                            <input type="color" class="form-control form-control-color" value="#007bff">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Welcome Message</label>
                                        <input type="text" class="form-control" value="Hello! How can I help you today?" placeholder="Enter welcome message">
                                    </div>
                                    <button type="button" class="btn btn-success">Save Settings</button>
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
                                        <strong>{{ \App\Models\ChatConversation::where('organization_id', auth()->user()->organization_id ?? 3)->count() }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Today's Chats:</span>
                                        <strong>{{ \App\Models\ChatConversation::where('organization_id', auth()->user()->organization_id ?? 3)->whereDate('created_at', today())->count() }}</strong>
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
    const code = `<script>
(function() {
    const script = document.createElement('script');
    script.src = 'https://ai-chat.support/widget/{{ auth()->user()->organization_id ?? 3 }}/script.js';
    script.async = true;
    document.head.appendChild(script);
})();
</script>`;
    
    navigator.clipboard.writeText(code).then(function() {
        alert('Widget code copied to clipboard!');
    });
}
</script>
@endsection
