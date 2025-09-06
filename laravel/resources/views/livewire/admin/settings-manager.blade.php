<div>
    <div class="row">
        <!-- Settings Navigation -->
        <div class="col-md-3">
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
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-md-9">
            @if($activeTab === 'payment')
                <!-- Payment Settings -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-credit-card"></i>
                            Payment Gateway Settings
                        </h4>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="savePaymentSettings">
                            <!-- PayPal Settings -->
                            <h5 class="mb-3">PayPal Configuration</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="paypal_mode" class="form-label">PayPal Mode</label>
                                        <select class="form-control" wire:model="paypal_mode">
                                            <option value="sandbox">Sandbox (Testing)</option>
                                            <option value="live">Live (Production)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="paypal_client_id" class="form-label">PayPal Client ID</label>
                                        <input type="text" class="form-control" wire:model="paypal_client_id" placeholder="PayPal Client ID">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="paypal_client_secret" class="form-label">PayPal Client Secret</label>
                                        <input type="password" class="form-control" wire:model="paypal_client_secret" placeholder="PayPal Client Secret">
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Razorpay Settings -->
                            <h5 class="mb-3">Razorpay Configuration (For Indian Customers)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="razorpay_key_id" class="form-label">Razorpay Key ID</label>
                                        <input type="text" class="form-control" wire:model="razorpay_key_id" placeholder="Razorpay Key ID">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="razorpay_key_secret" class="form-label">Razorpay Key Secret</label>
                                        <input type="password" class="form-control" wire:model="razorpay_key_secret" placeholder="Razorpay Key Secret">
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
                        <form wire:submit.prevent="savePaymentSettings">
                            <!-- PayPal Settings -->
                            <h5 class="mb-3"><i class="fab fa-paypal text-primary"></i> PayPal Configuration</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label for="paypal_mode" class="form-label">PayPal Mode</label>
                                        <select class="form-control" wire:model="paypal_mode">
                                            <option value="sandbox">Sandbox (Testing)</option>
                                            <option value="live">Live (Production)</option>
                                        </select>
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
</div>

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
