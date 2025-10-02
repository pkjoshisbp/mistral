<div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fab fa-whatsapp me-2"></i>
                        WhatsApp Business Integration
                    </h4>
                    <p class="text-muted mb-0">Connect your WhatsApp Business account to provide AI support via WhatsApp</p>
                </div>
                <div class="card-body">
                    @if (session()->has('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if (session()->has('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-8">
                            <!-- Connection Status -->
                            <div class="alert {{ $isConnected ? 'alert-success' : 'alert-warning' }} mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="fab fa-whatsapp fa-2x me-3"></i>
                                    <div>
                                        <h6 class="mb-1">
                                            {{ $isConnected ? 'WhatsApp Connected' : 'WhatsApp Not Connected' }}
                                        </h6>
                                        <small>{{ $connectionStatus }}</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Setup Instructions -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        WhatsApp Business API Setup Instructions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h6>Step 1: Facebook Business Account</h6>
                                            <ol class="small mb-3">
                                                <li>Go to <a href="https://business.facebook.com/" target="_blank" class="text-decoration-none">Facebook Business Manager</a></li>
                                                <li>Create or log into your business account</li>
                                                <li>Navigate to "WhatsApp Business Platform"</li>
                                                <li>Create a new WhatsApp Business Account</li>
                                            </ol>

                                            <h6>Step 2: WhatsApp Business API</h6>
                                            <ol class="small mb-3">
                                                <li>In Facebook Developer Console, create a new app</li>
                                                <li>Add "WhatsApp Business Platform" product</li>
                                                <li>Configure webhooks with the URL and verify token shown below</li>
                                                <li>Get your Access Token and Phone Number ID</li>
                                            </ol>

                                            <h6>Step 3: Webhook Configuration</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-bold">Webhook URL</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" value="{{ $webhookUrl }}" readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $webhookUrl }}')">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Use this URL in Facebook Developer Console</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small fw-bold">Verify Token</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" value="{{ $verifyToken }}" readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ $verifyToken }}')">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Organization-specific verify token</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuration Form -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-cog me-2"></i>
                                        WhatsApp Configuration
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form wire:submit.prevent="saveConfiguration">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Access Token <span class="text-danger">*</span></label>
                                                <input type="password" 
                                                       wire:model="accessToken" 
                                                       class="form-control @error('accessToken') is-invalid @enderror" 
                                                       placeholder="Enter your WhatsApp Access Token">
                                                @error('accessToken')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="text-muted">Get this from Facebook Developer Console</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Phone Number ID <span class="text-danger">*</span></label>
                              <input type="text" 
                                  wire:model="phoneNumberId" 
                                  class="form-control @error('phoneNumberId') is-invalid @enderror" 
                                  placeholder="e.g. 123456789012345 (WhatsApp Phone Number ID)"
                                  inputmode="numeric"
                                  autocomplete="off">
                                                @error('phoneNumberId')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="text-muted">WhatsApp Business phone number ID</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fab fa-whatsapp me-2"></i>Save Configuration
                                            </button>
                                            <button type="button" wire:click="testConnection" class="btn btn-outline-primary">
                                                <i class="fas fa-vial me-2"></i>Test Connection
                                            </button>
                                            @if($isConnected)
                                                <button type="button" wire:click="disconnect" class="btn btn-outline-danger">
                                                    <i class="fas fa-unlink me-2"></i>Disconnect
                                                </button>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Status and Features -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Integration Status
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Connection Status</span>
                                            <span class="badge {{ $isConnected ? 'bg-success' : 'bg-warning' }}">
                                                {{ $isConnected ? 'Connected' : 'Not Connected' }}
                                            </span>
                                        </div>
                                        <div class="progress mb-2" style="height: 6px;">
                                            <div class="progress-bar {{ $isConnected ? 'bg-success' : 'bg-warning' }}" 
                                                 style="width: {{ $isConnected ? '100' : '0' }}%"></div>
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-2">Features</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Auto-respond to WhatsApp messages
                                        </li>
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Organization-specific responses
                                        </li>
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            Rich media support
                                        </li>
                                        <li class="mb-1">
                                            <i class="fas fa-check text-success me-2"></i>
                                            24/7 automated support
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Important Notes -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Important Notes
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small">
                                        <li class="mb-2">
                                            <i class="fas fa-info-circle text-info me-1"></i>
                                            WhatsApp Business API approval required
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-warning me-1"></i>
                                            Setup can take 1-2 business days
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-dollar-sign text-primary me-1"></i>
                                            WhatsApp charges per message
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>