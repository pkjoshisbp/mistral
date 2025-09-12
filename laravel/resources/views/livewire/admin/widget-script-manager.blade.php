<div>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🔧 Widget Script Manager</h1>
                <p class="text-muted mb-0">Generate and customize AI chat widget scripts for organizations</p>
            </div>
        </div>

        <!-- Success Alert -->
        @if (session()->has('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <!-- Settings Panel -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cogs"></i> Widget Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Organization Selection -->
                        <div class="mb-4">
                            <label class="form-label">Select Organization *</label>
                            <select class="form-select @error('selectedOrganization') is-invalid @enderror" 
                                    wire:model.live="selectedOrganization">
                                <option value="">Choose organization...</option>
                                @foreach($this->organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @if(!$selectedOrganization)
                                <small class="form-text text-muted">Select an organization to generate widget script</small>
                            @endif
                        </div>

                        @if($selectedOrganization)
                            <div class="row">
                                <!-- Visual Settings -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Visual Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Widget Position</label>
                                        <select class="form-select" wire:model.live="widgetSettings.position">
                                            <option value="bottom-right">Bottom Right</option>
                                            <option value="bottom-left">Bottom Left</option>
                                            <option value="top-right">Top Right</option>
                                            <option value="top-left">Top Left</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Primary Color</label>
                                        <input type="color" class="form-control form-control-color" 
                                               wire:model.live="widgetSettings.primaryColor">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Theme</label>
                                        <select class="form-select" wire:model.live="widgetSettings.theme">
                                            <option value="default">Default</option>
                                            <option value="minimal">Minimal</option>
                                            <option value="modern">Modern</option>
                                            <option value="classic">Classic</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Content Settings -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Content Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Widget Title</label>
                                        <input type="text" class="form-control" 
                                               wire:model.live="widgetSettings.title">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Subtitle</label>
                                        <input type="text" class="form-control" 
                                               wire:model.live="widgetSettings.subtitle">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Greeting Message</label>
                                        <textarea class="form-control" rows="2" 
                                                  wire:model.live="widgetSettings.greeting"></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Input Placeholder</label>
                                        <input type="text" class="form-control" 
                                               wire:model.live="widgetSettings.placeholder">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Behavior Settings -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Behavior Settings</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Welcome Delay (ms)</label>
                                        <input type="number" class="form-control" 
                                               wire:model.live="widgetSettings.welcome_delay" 
                                               min="0" max="10000" step="500">
                                        <small class="form-text text-muted">Delay before showing welcome message</small>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" 
                                               wire:model.live="widgetSettings.auto_open">
                                        <label class="form-check-label">
                                            Auto-open widget on page load
                                        </label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" 
                                               wire:model.live="widgetSettings.collectEmail">
                                        <label class="form-check-label">
                                            Collect visitor email before chat
                                        </label>
                                    </div>
                                </div>

                                <!-- Analytics & Pages -->
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Analytics & Pages</h6>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" 
                                               wire:model.live="widgetSettings.analytics">
                                        <label class="form-check-label">
                                            Enable Analytics Tracking
                                        </label>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Show on Pages</label>
                                        <select class="form-select" wire:model.live="widgetSettings.showOnPages">
                                            <option value="all">All Pages</option>
                                            <option value="specific">Specific Pages Only</option>
                                            <option value="exclude">All Except Excluded</option>
                                        </select>
                                    </div>

                                    @if($widgetSettings['showOnPages'] === 'exclude')
                                        <div class="mb-3">
                                            <label class="form-label">Exclude Pages (one per line)</label>
                                            <textarea class="form-control" rows="3" 
                                                      wire:model.live="widgetSettings.excludePages" 
                                                      placeholder="/admin&#10;/login&#10;/checkout"></textarea>
                                            <small class="form-text text-muted">Enter page paths to exclude, one per line</small>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="button" class="btn btn-primary" wire:click="updateSettings">
                                    <i class="fas fa-save"></i> Update Settings
                                </button>
                                <button type="button" class="btn btn-success" wire:click="showScript">
                                    <i class="fas fa-code"></i> Generate Script
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-eye"></i> Widget Preview
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($selectedOrganization)
                            <div class="widget-preview" style="position: relative; height: 300px; background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                                <!-- Mock website content -->
                                <div class="p-3" style="background: white; height: 200px;">
                                    <div class="placeholder-glow">
                                        <span class="placeholder col-8"></span>
                                        <span class="placeholder col-6"></span>
                                        <span class="placeholder col-7"></span>
                                    </div>
                                </div>
                                
                                <!-- Widget preview -->
                                <div class="widget-mock" style="position: absolute; {{ $widgetSettings['position'] === 'bottom-right' ? 'bottom: 20px; right: 20px;' : '' }}{{ $widgetSettings['position'] === 'bottom-left' ? 'bottom: 20px; left: 20px;' : '' }}{{ $widgetSettings['position'] === 'top-right' ? 'top: 20px; right: 20px;' : '' }}{{ $widgetSettings['position'] === 'top-left' ? 'top: 20px; left: 20px;' : '' }}">
                                    <div class="widget-button" style="width: 60px; height: 60px; border-radius: 30px; background: {{ $widgetSettings['primaryColor'] }}; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer;">
                                        <i class="fas fa-comments text-white" style="font-size: 24px;"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <h6>Current Settings:</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>Position:</strong> {{ ucwords(str_replace('-', ' ', $widgetSettings['position'])) }}</li>
                                    <li><strong>Color:</strong> <span class="badge" style="background: {{ $widgetSettings['primaryColor'] }};">{{ $widgetSettings['primaryColor'] }}</span></li>
                                    <li><strong>Theme:</strong> {{ ucfirst($widgetSettings['theme']) }}</li>
                                    <li><strong>Analytics:</strong> {{ $widgetSettings['analytics'] ? 'Enabled' : 'Disabled' }}</li>
                                </ul>
                            </div>
                        @else
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-mouse-pointer fa-3x mb-3"></i>
                                <p>Select an organization to see widget preview</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Modal -->
    @if($showScriptModal)
        <div class="modal fade show" style="display: block;" tabindex="-1" aria-labelledby="scriptModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scriptModalLabel">
                            <i class="fas fa-code"></i> Widget Installation Script
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeScriptModal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Installation Instructions:</strong><br>
                            1. Copy the script below<br>
                            2. Paste it before the closing &lt;/body&gt; tag on your website<br>
                            3. The widget will automatically appear and start tracking analytics
                        </div>
                        
                        <div class="position-relative">
                            <textarea class="form-control" rows="20" readonly onclick="this.select()" style="font-family: 'Courier New', monospace; font-size: 12px;">{{ $generatedScript }}</textarea>
                            <button type="button" class="btn btn-sm btn-outline-primary position-absolute top-0 end-0 m-2" 
                                    onclick="navigator.clipboard.writeText(this.parentElement.querySelector('textarea').value); this.innerHTML='<i class='fas fa-check'></i> Copied!'; setTimeout(() => this.innerHTML='<i class='fas fa-copy'></i> Copy', 2000)">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <a href="https://ai-chat.support/docs/widget-installation" target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-book"></i> Installation Guide
                        </a>
                        <button type="button" class="btn btn-secondary" wire:click="closeScriptModal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
