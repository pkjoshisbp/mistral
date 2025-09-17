<div class="container-fluid">
    <div class="row g-3">
        <!-- Settings Navigation -->
        <div class="col-12 col-md-3 col-xl-2">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Settings</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <button wire:click="$set('activeTab', 'payment')" 
                                class="list-group-item list-group-item-action {{ $activeTab === 'payment' ? 'active' : '' }}">
                            <i class="fas fa-credit-card"></i>
                            Payment Settings
                        </button>
                        <button wire:click="$set('activeTab', 'email')" 
                                class="list-group-item list-group-item-action {{ $activeTab === 'email' ? 'active' : '' }}">
                            <i class="fas fa-envelope"></i>
                            Email Settings
                        </button>
                        <button wire:click="$set('activeTab', 'app')" 
                                class="list-group-item list-group-item-action {{ $activeTab === 'app' ? 'active' : '' }}">
                            <i class="fas fa-cog"></i>
                            Application
                        </button>
                        <button wire:click="$set('activeTab', 'ai')" 
                                class="list-group-item list-group-item-action {{ $activeTab === 'ai' ? 'active' : '' }}">
                            <i class="fas fa-robot"></i>
                            AI Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-12 col-md-9 col-xl-10">

            @if($activeTab === 'email')
                <!-- Email Settings -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-envelope"></i>
                            Email Configuration
                        </h4>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="saveEmailSettings">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_mailer" class="form-label">Mail Driver</label>
                                        <select class="form-control" wire:model="mail_mailer">
                                            <option value="smtp">SMTP</option>
                                            <option value="sendmail">Sendmail</option>
                                            <option value="mailgun">Mailgun</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control @error('mail_host') is-invalid @enderror" 
                                               wire:model="mail_host" placeholder="smtp.gmail.com">
                                        @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control @error('mail_port') is-invalid @enderror" 
                                               wire:model="mail_port" placeholder="587">
                                        @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_encryption" class="form-label">Encryption</label>
                                        <select class="form-control" wire:model="mail_encryption">
                                            <option value="tls">TLS</option>
                                            <option value="ssl">SSL</option>
                                            <option value="">None</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control @error('mail_username') is-invalid @enderror" 
                                               wire:model="mail_username" placeholder="your-email@gmail.com">
                                        @error('mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control @error('mail_password') is-invalid @enderror" 
                                               wire:model="mail_password" placeholder="App Password">
                                        @error('mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_from_address" class="form-label">From Address</label>
                                        <input type="email" class="form-control @error('mail_from_address') is-invalid @enderror" 
                                               wire:model="mail_from_address" placeholder="noreply@ai-chat.support">
                                        @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="mail_from_name" class="form-label">From Name</label>
                                        <input type="text" class="form-control @error('mail_from_name') is-invalid @enderror" 
                                               wire:model="mail_from_name" placeholder="AI Chat Support">
                                        @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" wire:click="testEmailSettings" class="btn btn-outline-primary">
                                    <i class="fas fa-paper-plane"></i>
                                    Send Test Email
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Email Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($activeTab === 'app')
                <!-- Application Settings -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-cog"></i>
                            Application Settings
                        </h4>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="saveAppSettings">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="app_name" class="form-label">Application Name</label>
                                        <input type="text" class="form-control @error('app_name') is-invalid @enderror" 
                                               wire:model="app_name" placeholder="AI Agent System">
                                        @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="app_url" class="form-label">Application URL</label>
                                        <input type="url" class="form-control @error('app_url') is-invalid @enderror" 
                                               wire:model="app_url" placeholder="https://ai-chat.support">
                                        @error('app_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="app_timezone" class="form-label">Timezone</label>
                                        <select class="form-control @error('app_timezone') is-invalid @enderror" wire:model="app_timezone">
                                            <option value="UTC">UTC</option>
                                            <option value="Asia/Kolkata">Asia/Kolkata</option>
                                            <option value="America/New_York">America/New_York</option>
                                            <option value="Europe/London">Europe/London</option>
                                        </select>
                                        @error('app_timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Application Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($activeTab === 'ai')
                <!-- AI Settings -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-robot"></i>
                            AI Model Settings
                        </h5>
                        <small class="text-muted">Configure AI providers and models for chat responses</small>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="saveAiSettings">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ai_model_provider">AI Provider</label>
                                        <select wire:model="ai_model_provider" class="form-control @error('ai_model_provider') is-invalid @enderror">
                                            <option value="llama">Llama (Local Models)</option>
                                            <option value="openai">OpenAI (GPT)</option>
                                        </select>
                                        @error('ai_model_provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">Choose between local Llama models or OpenAI's GPT models</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6" x-show="$wire.ai_model_provider === 'llama'">
                                    <div class="form-group">
                                        <label for="ai_backend_type">Llama Backend</label>
                                        <select wire:model="ai_backend_type" class="form-control @error('ai_backend_type') is-invalid @enderror">
                                            @foreach($this->getAvailableBackends() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('ai_backend_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">Choose between Ollama (easy) or llama.cpp (optimized)</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Ollama Settings -->
                            <div class="row" x-show="$wire.ai_model_provider === 'llama' && $wire.ai_backend_type === 'ollama'">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="llama_default_model">Ollama Model</label>
                                        <select wire:model="llama_default_model" 
                                                class="form-control @error('llama_default_model') is-invalid @enderror">
                                            @foreach($this->getAvailableLlamaModels() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('llama_default_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">Available Ollama models (auto-downloaded on first use)</small>
                                    </div>
                                </div>
                            </div>

                            <!-- llama.cpp Settings -->
                            <div x-show="$wire.ai_model_provider === 'llama' && $wire.ai_backend_type === 'llamacpp'">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="llamacpp_model_repo">Pre-configured GGUF Models</label>
                                            <select wire:model="llamacpp_model_repo" 
                                                    class="form-control @error('llamacpp_model_repo') is-invalid @enderror">
                                                @foreach($this->getAvailableLlamaCppModels() as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('llamacpp_model_repo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="form-text text-muted">
                                                <i class="fas fa-download"></i> 
                                                Select from tested GGUF models (will auto-download if needed)
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="llamacpp_model_path">OR Custom Model File Path</label>
                                            <input type="text" wire:model="llamacpp_model_path" 
                                                   class="form-control @error('llamacpp_model_path') is-invalid @enderror"
                                                   placeholder="/path/to/custom-model.gguf">
                                            @error('llamacpp_model_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="form-text text-muted">Optional: Full path to custom GGUF model file (overrides above selection)</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="llamacpp_threads">CPU Threads</label>
                                            <input type="number" wire:model="llamacpp_threads" 
                                                   class="form-control @error('llamacpp_threads') is-invalid @enderror"
                                                   min="1" max="32" placeholder="4">
                                            @error('llamacpp_threads')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="form-text text-muted">Number of CPU threads (1-32)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="llamacpp_context_length">Context Length</label>
                                            <input type="number" wire:model="llamacpp_context_length" 
                                                   class="form-control @error('llamacpp_context_length') is-invalid @enderror"
                                                   min="512" max="8192" step="512" placeholder="4096">
                                            @error('llamacpp_context_length')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="form-text text-muted">Context window size (512-8192)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="openai_api_key">OpenAI API Key</label>
                                        <input type="password" wire:model="openai_api_key" 
                                               class="form-control @error('openai_api_key') is-invalid @enderror"
                                               placeholder="sk-proj-...">
                                        @error('openai_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">Required only when using OpenAI provider</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="openai_default_model">OpenAI Model</label>
                                        <select wire:model="openai_default_model" class="form-control @error('openai_default_model') is-invalid @enderror">
                                            <option value="gpt-5-mini">GPT-5 Mini (Only Available Model)</option>
                                        </select>
                                        @error('openai_default_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">GPT-5-mini is the only allowed model for your account</small>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Current Configuration:</strong> 
                                @if($ai_model_provider === 'openai')
                                    <span class="badge badge-primary">OpenAI GPT</span>
                                    Using GPT-5 Mini model
                                @else
                                    @if($ai_backend_type === 'ollama')
                                        <span class="badge badge-success">Ollama</span>
                                        Using {{ $llama_default_model }} model
                                    @else
                                        <span class="badge badge-warning">llama.cpp</span>
                                        Using custom GGUF model @if($llamacpp_model_path) ({{ basename($llamacpp_model_path) }}) @endif
                                    @endif
                                @endif
                            </div>

                            <!-- Performance Tips -->
                            @if($ai_model_provider === 'llama')
                                <div class="alert alert-light">
                                    <h6><i class="fas fa-lightbulb"></i> Performance Analysis (Tested Results)</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>🚀 Backend Performance:</h6>
                                            <ul class="mb-2">
                                                <li><strong>Ollama:</strong> 7.33s response time, easy setup</li>
                                                <li><strong>llama.cpp:</strong> 6.15s response time (16% faster)</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>📊 Model Performance:</h6>
                                            <ul class="mb-2">
                                                <li><strong>llama3.2:1b:</strong> 9.86s (fastest)</li>
                                                <li><strong>llama3.2:3b:</strong> 14.47s (recommended)</li>
                                                <li><strong>mistral:7b:</strong> 27.66s (highest quality)</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i>
                                            Results from live testing on this server. llama.cpp provides 16% performance gain but requires GGUF model files.
                                        </small>
                                    </div>
                                </div>
                            @endif

                            <!-- Model Testing Section -->
                            @if($ai_model_provider === 'llama' && $ai_backend_type === 'ollama')
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Model Testing</label>
                                            <div class="btn-group d-block">
                                                @foreach($this->getAvailableLlamaModels() as $value => $label)
                                                    <button type="button" 
                                                            wire:click="testModel('{{ $value }}')" 
                                                            class="btn btn-outline-info btn-sm mr-2 mb-2"
                                                            wire:loading.attr="disabled" 
                                                            wire:target="testModel">
                                                        <span wire:loading.remove wire:target="testModel('{{ $value }}')">
                                                            <i class="fas fa-flask"></i>
                                                            Test {{ $value }}
                                                        </span>
                                                        <span wire:loading wire:target="testModel('{{ $value }}')" class="d-none">
                                                            <i class="fas fa-spinner fa-spin"></i>
                                                            Testing...
                                                        </span>
                                                    </button>
                                                @endforeach
                                            </div>
                                            <small class="form-text text-muted">Test different models to check response time and availability</small>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between">
                                <a href="/AI_MODEL_RECOMMENDATIONS.md" target="_blank" class="btn btn-info">
                                    <i class="fas fa-chart-line"></i>
                                    View Performance Analysis
                                </a>
                                
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveAiSettings">
                                    <span wire:loading.remove wire:target="saveAiSettings">
                                        <i class="fas fa-save"></i>
                                        Save AI Settings
                                    </span>
                                    <span wire:loading wire:target="saveAiSettings">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Saving...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($activeTab === 'payment')
                <!-- Payment Settings -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-credit-card"></i>
                            Payment Settings
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Important Notice -->
                        <div class="alert alert-info mb-4">
                            <h6><i class="fas fa-info-circle"></i> Important Notice</h6>
                            <p class="mb-2"><strong>These settings are automatically synced with your .env file.</strong></p>
                            <ul class="mb-0">
                                <li>Use <strong>Sandbox mode</strong> for testing with test credentials</li>
                                <li>Switch to <strong>Live mode</strong> for production with real credentials</li>
                                <li>Changes here will update both database and .env file automatically</li>
                                <li>No need to manually edit .env file - use this panel instead</li>
                            </ul>
                        </div>
                        
                        <form wire:submit.prevent="savePaymentSettings">
                            <!-- PayPal Settings -->
                            <h5 class="mb-3"><i class="fab fa-paypal text-primary"></i> PayPal Configuration</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="paypal_mode" class="form-label">PayPal Mode 
                                            <span class="badge badge-{{ $paypal_mode === 'live' ? 'success' : 'warning' }}">
                                                {{ $paypal_mode === 'live' ? 'LIVE' : 'SANDBOX' }}
                                            </span>
                                        </label>
                                        <select class="form-control" wire:model="paypal_mode" wire:change="$refresh">
                                            <option value="sandbox">Sandbox (Testing)</option>
                                            <option value="live">Live (Production)</option>
                                        </select>
                                        <small class="text-muted">
                                            @if($paypal_mode === 'sandbox')
                                                Use test credentials from PayPal Developer Dashboard
                                            @else
                                                Use live credentials from PayPal business account
                                            @endif
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="paypal_client_id" class="form-label">PayPal Client ID</label>
                                        <input type="text" class="form-control @error('paypal_client_id') is-invalid @enderror" 
                                               wire:model="paypal_client_id" placeholder="PayPal Client ID">
                                        @error('paypal_client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="paypal_client_secret" class="form-label">PayPal Client Secret</label>
                                        <input type="password" class="form-control @error('paypal_client_secret') is-invalid @enderror" 
                                               wire:model="paypal_client_secret" placeholder="PayPal Client Secret">
                                        @error('paypal_client_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="paypal_webhook_url" class="form-label">PayPal Webhook URL</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   value="{{ $paypal_webhook_url }}" 
                                                   id="paypal_webhook_url" readonly>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="copyToClipboard('paypal_webhook_url')" 
                                                    title="Copy to clipboard">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Configure this URL in your PayPal Developer Dashboard webhooks settings.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Razorpay Settings -->
                            <h5 class="mb-3"><i class="fas fa-rupee-sign text-primary"></i> Razorpay Configuration</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="razorpay_key_id" class="form-label">Razorpay Key ID</label>
                                        <input type="text" class="form-control @error('razorpay_key_id') is-invalid @enderror" 
                                               wire:model="razorpay_key_id" placeholder="Razorpay Key ID">
                                        @error('razorpay_key_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="razorpay_key_secret" class="form-label">Razorpay Key Secret</label>
                                        <input type="password" class="form-control @error('razorpay_key_secret') is-invalid @enderror" 
                                               wire:model="razorpay_key_secret" placeholder="Razorpay Key Secret">
                                        @error('razorpay_key_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="razorpay_webhook_url" class="form-label">Razorpay Webhook URL</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   value="{{ $razorpay_webhook_url }}" 
                                                   id="razorpay_webhook_url" readonly>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="copyToClipboard('razorpay_webhook_url')" 
                                                    title="Copy to clipboard">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">
                                            Configure this URL in your Razorpay Dashboard webhooks settings.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Save Payment Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>


    </div> <!-- Close main container-fluid div -->

<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(element.value).then(function() {
        // Show success message
        const button = element.nextElementSibling;
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check text-success"></i>';
        
        setTimeout(() => {
            button.innerHTML = originalIcon;
        }, 2000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        // Fallback for older browsers
        document.execCommand('copy');
    });
}
</script>