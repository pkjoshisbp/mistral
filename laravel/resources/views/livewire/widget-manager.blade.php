<div class="card">
    <div class="card-header">
        <h3 class="card-title">Widget Management</h3>
    </div>

    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('error') }}
            </div>
        @endif

        <div class="row">
            <!-- Organization Selection -->
            <div class="col-md-4">
                <div class="form-group">
                    <label>Select Organization</label>
                    <select class="form-control" wire:model="selectedOrgId" wire:change="selectOrganization($event.target.value)">
                        <option value="">Choose an organization...</option>
                        @foreach ($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($selectedOrgId)
            <div class="row">
                <!-- Widget Settings -->
                <div class="col-md-6">
                    <h5>Widget Settings</h5>
                    
                    <div class="form-group">
                        <label>Welcome Message</label>
                        <input type="text" wire:model="settings.welcome_message" class="form-control" placeholder="Hello! How can I help you today?">
                        @error('settings.welcome_message') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Primary Color</label>
                                <input type="color" wire:model="settings.primary_color" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Widget Position</label>
                                <select wire:model="settings.widget_position" class="form-control">
                                    <option value="bottom-right">Bottom Right</option>
                                    <option value="bottom-left">Bottom Left</option>
                                    <option value="top-right">Top Right</option>
                                    <option value="top-left">Top Left</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Secondary Color</label>
                                <input type="color" wire:model="settings.secondary_color" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Text Color</label>
                                <input type="color" wire:model="settings.text_color" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Border Radius</label>
                        <select wire:model="settings.border_radius" class="form-control">
                            <option value="5px">Small (5px)</option>
                            <option value="10px">Medium (10px)</option>
                            <option value="15px">Large (15px)</option>
                            <option value="25px">Extra Large (25px)</option>
                        </select>
                    </div>

                    <button wire:click="saveSettings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>

                <!-- Widget Preview -->
                <div class="col-md-6">
                    <h5>Widget Preview</h5>
                    <div class="widget-preview" style="background: #f8f9fa; padding: 20px; border-radius: 10px; position: relative; height: 300px;">
                        <!-- Simulated chat widget -->
                        <div style="position: absolute; {{ $settings['widget_position'] === 'bottom-right' ? 'bottom: 20px; right: 20px;' : '' }}{{ $settings['widget_position'] === 'bottom-left' ? 'bottom: 20px; left: 20px;' : '' }}{{ $settings['widget_position'] === 'top-right' ? 'top: 20px; right: 20px;' : '' }}{{ $settings['widget_position'] === 'top-left' ? 'top: 20px; left: 20px;' : '' }}">
                            <!-- Chat button -->
                            <div style="width: 60px; height: 60px; background: {{ $settings['primary_color'] }}; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                                <i class="fas fa-comments"></i>
                            </div>
                        </div>
                        
                        <div class="text-muted text-center" style="margin-top: 100px;">
                            <i class="fas fa-eye fa-2x mb-2"></i>
                            <p>Preview of widget position: <strong>{{ ucwords(str_replace('-', ' ', $settings['widget_position'])) }}</strong></p>
                            <p>Primary color: <span style="color: {{ $settings['primary_color'] }}">{{ $settings['primary_color'] }}</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Embed Code -->
            <div class="row mt-4">
                <div class="col-12">
                    <h5>Embed Code</h5>
                    <p class="text-muted">Copy this code and paste it into your website's HTML, preferably before the closing &lt;/body&gt; tag.</p>
                    
                    <div class="form-group">
                        <textarea class="form-control" rows="6" readonly>{{ $embedCode }}</textarea>
                    </div>

                    <button onclick="copyToClipboard()" class="btn btn-success">
                        <i class="fas fa-copy"></i> Copy Embed Code
                    </button>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Integration Steps:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Copy the embed code above</li>
                            <li>Paste it into your website's HTML before the closing &lt;/body&gt; tag</li>
                            <li>Make sure your organization has synced data in Qdrant</li>
                            <li>The widget will automatically appear on your website</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Test Widget -->
            <div class="row mt-4">
                <div class="col-12">
                    <h5>Test Widget</h5>
                    <p class="text-muted">You can test the widget functionality here:</p>
                    
                    <div class="card">
                        <div class="card-body">
                            <iframe src="{{ route('widget.test', $selectedOrgId) }}" width="100%" height="600" style="border: 1px solid #ddd; border-radius: 5px;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
function copyToClipboard() {
    const textarea = document.querySelector('textarea[readonly]');
    textarea.select();
    document.execCommand('copy');
    
    // Show success message
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.classList.remove('btn-success');
    button.classList.add('btn-primary');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-primary');
        button.classList.add('btn-success');
    }, 2000);
}
</script>
