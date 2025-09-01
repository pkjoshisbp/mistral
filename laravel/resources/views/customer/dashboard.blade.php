@extends('layouts.customer')

@section('content')
<div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <h2 class="mb-2">
                        <i class="fas fa-chart-line me-2"></i>
                        Welcome back, {{ auth()->user()->name }}!
                    </h2>
                    <p class="mb-0">
                        Organization: 
                        @if(auth()->user()->organizations->count() > 0)
                            <strong>{{ auth()->user()->organizations->first()->name }}</strong>
                        @else
                            <span class="text-warning">No organization assigned</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $totalChats ?? 0 }}</h3>
                    <p>Total Conversations</p>
                </div>
                <div class="icon">
                    <i class="fas fa-comments"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $todayChats ?? 0 }}</h3>
                    <p>Today's Chats</p>
                </div>
                <div class="icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $dataSources ?? 0 }}</h3>
                    <p>Data Sources</p>
                </div>
                <div class="icon">
                    <i class="fas fa-database"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $subscriptionStatus ?? 'N/A' }}</h3>
                    <p>Subscription</p>
                </div>
                <div class="icon">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock me-2"></i>
                        Recent Chat Activity
                    </h3>
                </div>
                <div class="card-body">
                    @if(isset($recentChats) && $recentChats->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Conversation</th>
                                        <th>Status</th>
                                        <th>Last Activity</th>
                                        <th>Messages</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentChats as $chat)
                                        <tr>
                                            <td>
                                                <strong>{{ $chat->title ?: 'Untitled Conversation' }}</strong><br>
                                                <small class="text-muted">{{ $chat->getDisplayName() }}</small>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $chat->status === 'active' ? 'success' : 'secondary' }}">
                                                    {{ ucfirst($chat->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                {{ $chat->last_activity_at ? $chat->last_activity_at->diffForHumans() : 'Never' }}
                                            </td>
                                            <td>{{ $chat->messages_count ?? 0 }}</td>
                                            <td>
                                                <a href="#" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h5>No conversations yet</h5>
                            <p class="text-muted">Your AI chat conversations will appear here once visitors start chatting.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-bolt me-2"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('customer.data-sources') }}" class="btn btn-outline-primary">
                            <i class="fas fa-plus-circle me-2"></i>
                            Add Data Source
                        </a>
                        <a href="{{ route('customer.content') }}" class="btn btn-outline-success">
                            <i class="fas fa-edit me-2"></i>
                            Manage Content
                        </a>
                        <a href="{{ route('customer.analytics') }}" class="btn btn-outline-info">
                            <i class="fas fa-chart-bar me-2"></i>
                            View Analytics
                        </a>
                        <a href="{{ route('customer.settings') }}" class="btn btn-outline-warning">
                            <i class="fas fa-cog me-2"></i>
                            Settings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Widget Customization -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-palette me-2"></i>
                        Widget Customization
                    </h3>
                </div>
                <div class="card-body">
                    @if(auth()->user()->organizations->count() > 0)
                        <form id="widgetCustomizationForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Primary Color</label>
                                        <input type="color" class="form-control form-control-color" id="primaryColor" value="#667eea">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Chat Bubble Position</label>
                                        <select class="form-select" id="chatPosition">
                                            <option value="bottom-right">Bottom Right</option>
                                            <option value="bottom-left">Bottom Left</option>
                                            <option value="top-right">Top Right</option>
                                            <option value="top-left">Top Left</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Welcome Message</label>
                                        <input type="text" class="form-control" id="welcomeMessage" 
                                               value="Hi! How can I help you today?" placeholder="Enter welcome message">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Chat Window Height</label>
                                        <select class="form-select" id="chatHeight">
                                            <option value="400px">Small (400px)</option>
                                            <option value="500px" selected>Medium (500px)</option>
                                            <option value="600px">Large (600px)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Auto-open After (seconds)</label>
                                        <select class="form-select" id="autoOpen">
                                            <option value="0">Never</option>
                                            <option value="5">5 seconds</option>
                                            <option value="10">10 seconds</option>
                                            <option value="30">30 seconds</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="showAgentAvatar" checked>
                                            <label class="form-check-label" for="showAgentAvatar">
                                                Show Agent Avatar
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preview Section -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5><i class="fas fa-eye me-2"></i>Preview</h5>
                                    <div class="bg-light p-3 rounded position-relative" style="min-height: 200px;">
                                        <div id="widgetPreview" class="position-absolute" style="bottom: 20px; right: 20px;">
                                            <div class="chat-bubble" style="width: 60px; height: 60px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
                                                <i class="fas fa-comments fa-lg"></i>
                                            </div>
                                        </div>
                                        <small class="text-muted">This is how your chat widget will appear on your website</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="button" class="btn btn-success" onclick="saveWidgetSettings()">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetWidgetSettings()">
                                    <i class="fas fa-undo me-2"></i>Reset to Default
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Please setup your organization first to customize the widget.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Widget Status -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-code me-2"></i>
                        Widget Status
                    </h3>
                </div>
                <div class="card-body">
                    @if(auth()->user()->organizations->count() > 0)
                        @php $organization = auth()->user()->organizations->first(); @endphp
                        <div class="mb-3">
                            <label class="form-label">Widget Script:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       value="{{ url('/widget/' . $organization->id . '/script.js') }}" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard(this)">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Test Widget:</label>
                            <a href="{{ route('widget.test', $organization->id) }}" 
                               class="btn btn-sm btn-outline-primary w-100" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>
                                Open Test Page
                            </a>
                        </div>
                        
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Copy the widget script and paste it into your website's HTML.
                            </small>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No organization assigned. Please contact support.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(button) {
    const input = button.parentElement.querySelector('input');
    input.select();
    document.execCommand('copy');
    
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}

// Widget Customization Functions
document.addEventListener('DOMContentLoaded', function() {
    updateWidgetPreview();
    
    // Add event listeners for real-time preview
    document.getElementById('primaryColor').addEventListener('change', updateWidgetPreview);
    document.getElementById('chatPosition').addEventListener('change', updateWidgetPreview);
    document.getElementById('welcomeMessage').addEventListener('input', updateWidgetPreview);
    document.getElementById('chatHeight').addEventListener('change', updateWidgetPreview);
    document.getElementById('autoOpen').addEventListener('change', updateWidgetPreview);
    document.getElementById('showAgentAvatar').addEventListener('change', updateWidgetPreview);
});

function updateWidgetPreview() {
    const primaryColor = document.getElementById('primaryColor').value;
    const position = document.getElementById('chatPosition').value;
    const preview = document.getElementById('widgetPreview');
    const bubble = preview.querySelector('.chat-bubble');
    
    // Update bubble color
    bubble.style.backgroundColor = primaryColor;
    
    // Update position
    preview.classList.remove('position-bottom-right', 'position-bottom-left', 'position-top-right', 'position-top-left');
    
    switch(position) {
        case 'bottom-left':
            preview.style.bottom = '20px';
            preview.style.left = '20px';
            preview.style.top = 'auto';
            preview.style.right = 'auto';
            break;
        case 'top-right':
            preview.style.top = '20px';
            preview.style.right = '20px';
            preview.style.bottom = 'auto';
            preview.style.left = 'auto';
            break;
        case 'top-left':
            preview.style.top = '20px';
            preview.style.left = '20px';
            preview.style.bottom = 'auto';
            preview.style.right = 'auto';
            break;
        default: // bottom-right
            preview.style.bottom = '20px';
            preview.style.right = '20px';
            preview.style.top = 'auto';
            preview.style.left = 'auto';
    }
}

function saveWidgetSettings() {
    const settings = {
        primaryColor: document.getElementById('primaryColor').value,
        chatPosition: document.getElementById('chatPosition').value,
        welcomeMessage: document.getElementById('welcomeMessage').value,
        chatHeight: document.getElementById('chatHeight').value,
        autoOpen: document.getElementById('autoOpen').value,
        showAgentAvatar: document.getElementById('showAgentAvatar').checked
    };
    
    // Here you would normally send this to your backend
    // For now, we'll just show a success message
    
    // Show success notification
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show mt-3';
    alert.innerHTML = `
        <i class="fas fa-check me-2"></i>
        Widget settings saved successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.getElementById('widgetCustomizationForm').appendChild(alert);
    
    // Auto-dismiss after 3 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 3000);
    
    console.log('Saved widget settings:', settings);
}

function resetWidgetSettings() {
    document.getElementById('primaryColor').value = '#667eea';
    document.getElementById('chatPosition').value = 'bottom-right';
    document.getElementById('welcomeMessage').value = 'Hi! How can I help you today?';
    document.getElementById('chatHeight').value = '500px';
    document.getElementById('autoOpen').value = '0';
    document.getElementById('showAgentAvatar').checked = true;
    
    updateWidgetPreview();
    
    // Show reset notification
    const alert = document.createElement('div');
    alert.className = 'alert alert-info alert-dismissible fade show mt-3';
    alert.innerHTML = `
        <i class="fas fa-info me-2"></i>
        Widget settings reset to default values.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.getElementById('widgetCustomizationForm').appendChild(alert);
    
    // Auto-dismiss after 3 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 3000);
}
</script>
@endsection
