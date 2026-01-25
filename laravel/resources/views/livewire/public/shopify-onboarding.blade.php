<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">🎉 Welcome to AI Chat Support!</h1>
                <p class="lead text-muted">Your Shopify app is installed. Let's get your AI chat widget live on your store.</p>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" 
                         role="progressbar" 
                         style="width: {{ ($currentStep / 5) * 100 }}%"
                         aria-valuenow="{{ $currentStep }}" 
                         aria-valuemin="0" 
                         aria-valuemax="5">
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Step {{ $currentStep }} of 5</small>
            </div>

            <!-- Steps Card -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    
                    @if($currentStep == 1)
                    <!-- Step 1: Installation Complete -->
                    <div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-check fs-4"></i>
                            </div>
                            <div>
                                <h3 class="mb-0">Installation Complete!</h3>
                                <p class="text-muted mb-0">Your app is connected to {{ $organization->name }}</p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info border-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Important:</strong> The chat widget is not yet visible on your store. You need to enable it in your theme editor (next step).
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card bg-light border-0 h-100">
                                    <div class="card-body">
                                        <h5 class="text-success"><i class="fas fa-check-circle me-2"></i>Completed</h5>
                                        <ul class="mb-0 ps-3">
                                            <li>App installed on Shopify</li>
                                            <li>Organization created</li>
                                            <li>AI backend connected</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-3 mt-md-0">
                                <div class="card bg-light border-0 h-100">
                                    <div class="card-body">
                                        <h5 class="text-warning"><i class="fas fa-clock me-2"></i>Next Steps</h5>
                                        <ul class="mb-0 ps-3">
                                            <li>Enable widget in theme editor</li>
                                            <li>Customize appearance</li>
                                            <li>Add training data</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($currentStep == 2)
                    <!-- Step 2: Enable Widget in Theme Editor -->
                    <div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <strong>2</strong>
                            </div>
                            <div>
                                <h3 class="mb-0">Enable Widget in Theme Editor</h3>
                                <p class="text-muted mb-0">Add the AI Chat Support app embed to your theme</p>
                            </div>
                        </div>
                        
                        <div class="alert alert-primary border-0 mb-4">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Shopify Requirement:</strong> All theme app extensions must be enabled by you in the theme editor. The app cannot automatically add itself to your store.
                        </div>
                        
                        <!-- Quick Start Button -->
                        <div class="text-center mb-4 p-4 bg-success bg-opacity-10 rounded border border-success">
                            <h5 class="mb-3 text-success"><i class="fas fa-rocket me-2"></i>Quick Start (Recommended)</h5>
                            <a href="{{ $deepLink }}" target="_blank" class="btn btn-success btn-lg">
                                <i class="fas fa-external-link-alt me-2"></i>
                                Open Theme Editor & Activate Widget
                            </a>
                            <p class="text-muted mt-3 mb-0 small">This link opens your current theme with the AI Chat Support widget ready to enable</p>
                        </div>
                        
                        <div class="accordion mb-4" id="detailedInstructions">
                            <!-- Manual Instructions -->
                            <div class="accordion-item border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#manualSteps">
                                        <i class="fas fa-list-ol me-2"></i>Manual Step-by-Step Instructions
                                    </button>
                                </h2>
                                <div id="manualSteps" class="accordion-collapse collapse" data-bs-parent="#detailedInstructions">
                                    <div class="accordion-body">
                                        <h6 class="fw-bold mb-3">Follow these steps to enable the widget:</h6>
                                        <ol class="mb-0">
                                            <li class="mb-3">
                                                <strong>Step 1: Select Your Theme</strong>
                                                <ul class="mt-2">
                                                    <li>Go to Shopify Admin → <strong>Online Store</strong> → <strong>Themes</strong></li>
                                                    <li>Click <strong>Customize</strong> on your active theme (or any theme you want to use)</li>
                                                </ul>
                                                {{-- Screenshot placeholder --}}
                                                <div class="mt-2 p-3 bg-light border rounded text-center">
                                                    <img src="{{ asset('images/onboarding/step1-themes.png') }}" 
                                                         alt="Navigate to Themes and click Customize" 
                                                         class="img-fluid rounded"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                    <div style="display:none;" class="text-muted small">
                                                        <i class="fas fa-image me-1"></i>Screenshot: Shopify Admin → Online Store → Themes → Customize button
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="mb-3">
                                                <strong>Step 2: Access App Embeds</strong>
                                                <ul class="mt-2">
                                                    <li>In the theme editor, click the <strong>App embeds</strong> icon in the left sidebar</li>
                                                    <li>Look for <strong>"AI Chat Support"</strong> in the list</li>
                                                    <li>Toggle the switch to <strong>ON</strong> (enabled)</li>
                                                </ul>
                                                {{-- Screenshot placeholder --}}
                                                <div class="mt-2 p-3 bg-light border rounded text-center">
                                                    <img src="{{ asset('images/onboarding/step2-app-embeds.png') }}" 
                                                         alt="Enable AI Chat Support in App Embeds" 
                                                         class="img-fluid rounded"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                    <div style="display:none;" class="text-muted small">
                                                        <i class="fas fa-image me-1"></i>Screenshot: Theme Editor → App embeds → AI Chat Support toggle ON
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="mb-3">
                                                <strong>Step 3: Click Save</strong>
                                                <ul class="mt-2">
                                                    <li>Click the <strong>Save</strong> button in the top right</li>
                                                    <li>The widget is now live on all pages of your store!</li>
                                                </ul>
                                                {{-- Screenshot placeholder --}}
                                                <div class="mt-2 p-3 bg-light border rounded text-center">
                                                    <img src="{{ asset('images/onboarding/step3-save.png') }}" 
                                                         alt="Save your theme changes" 
                                                         class="img-fluid rounded"
                                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                                    <div style="display:none;" class="text-muted small">
                                                        <i class="fas fa-image me-1"></i>Screenshot: Save button in theme editor
                                                    </div>
                                                </div>
                                            </li>
                                        </ol>
                                        <div class="alert alert-info border-0 mt-3 mb-0">
                                            <strong>Note:</strong> App embeds appear on <strong>all pages</strong> of your theme automatically once enabled.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Activate/Deactivate -->
                            <div class="accordion-item border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#toggleWidget">
                                        <i class="fas fa-toggle-on me-2"></i>How to Activate & Deactivate
                                    </button>
                                </h2>
                                <div id="toggleWidget" class="accordion-collapse collapse" data-bs-parent="#detailedInstructions">
                                    <div class="accordion-body">
                                        <h6 class="fw-bold mb-3">Managing Widget Visibility:</h6>
                                        
                                        {{-- Screenshot placeholder for toggle action --}}
                                        <div class="mb-3 p-3 bg-light border rounded text-center">
                                            <img src="{{ asset('images/onboarding/toggle-widget.png') }}" 
                                                 alt="Toggle AI Chat Support ON/OFF" 
                                                 class="img-fluid rounded"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                            <div style="display:none;" class="text-muted small">
                                                <i class="fas fa-image me-1"></i>Screenshot: App embeds toggle switch for ON/OFF
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card bg-success bg-opacity-10 border-success mb-3">
                                                    <div class="card-body">
                                                        <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>To Activate:</h6>
                                                        <ol class="mb-0 small">
                                                            <li>Go to Theme Editor</li>
                                                            <li>Click <strong>App embeds</strong></li>
                                                            <li>Toggle <strong>AI Chat Support</strong> to <span class="badge bg-success">ON</span></li>
                                                            <li>Click <strong>Save</strong></li>
                                                        </ol>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card bg-secondary bg-opacity-10 border-secondary mb-3">
                                                    <div class="card-body">
                                                        <h6 class="text-secondary"><i class="fas fa-times-circle me-2"></i>To Deactivate:</h6>
                                                        <ol class="mb-0 small">
                                                            <li>Go to Theme Editor</li>
                                                            <li>Click <strong>App embeds</strong></li>
                                                            <li>Toggle <strong>AI Chat Support</strong> to <span class="badge bg-secondary">OFF</span></li>
                                                            <li>Click <strong>Save</strong></li>
                                                        </ol>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-warning border-0 mb-0">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Important:</strong> Changes only apply to the specific theme you're editing. If you switch themes, you'll need to enable it again.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Supported Templates -->
                            <div class="accordion-item border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#templates">
                                        <i class="fas fa-file-alt me-2"></i>Supported Templates
                                    </button>
                                </h2>
                                <div id="templates" class="accordion-collapse collapse" data-bs-parent="#detailedInstructions">
                                    <div class="accordion-body">
                                        <h6 class="fw-bold mb-3">The AI Chat Support widget appears on:</h6>
                                        
                                        {{-- Screenshot placeholder for widget on storefront --}}
                                        <div class="mb-3 p-3 bg-light border rounded text-center">
                                            <img src="{{ asset('images/onboarding/widget-preview.png') }}" 
                                                 alt="Widget appearing on storefront" 
                                                 class="img-fluid rounded"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                            <div style="display:none;" class="text-muted small">
                                                <i class="fas fa-image me-1"></i>Screenshot: Chat widget visible on store pages
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <span>Home Page</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <span>Product Pages</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <span>Collection Pages</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <span>Cart Page</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <span>Pages (About, Contact, etc.)</span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center p-2 bg-light rounded">
                                                    <i class="fas fa-check text-success me-2"></i>
                                                    <span>Blog & Articles</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-success border-0 mt-3 mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>App Embed = Site-Wide:</strong> The widget automatically appears on <strong>all pages</strong> once enabled. No need to add it to individual templates!
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-muted small text-center">
                            <i class="fas fa-question-circle me-1"></i>
                            Need help? <a href="https://help.shopify.com/en/manual/online-store/themes/theme-structure/extend/apps#enable-app-embed" target="_blank">View Shopify's guide on app embeds</a>
                        </div>
                    </div>
                    @endif

                    @if($currentStep == 3)
                    <!-- Step 3: Customize Widget -->
                    <div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <strong>3</strong>
                            </div>
                            <div>
                                <h3 class="mb-0">Customize Widget Appearance</h3>
                                <p class="text-muted mb-0">Make it match your brand</p>
                            </div>
                        </div>
                        
                        <div class="card bg-primary bg-opacity-10 border-primary mb-4">
                            <div class="card-body">
                                <h6 class="text-primary mb-3"><i class="fas fa-palette me-2"></i>Customization Options</h6>
                                <p class="mb-0">You can customize colors, position, size, welcome message, and more directly in the Shopify theme editor.</p>
                            </div>
                        </div>
                        
                        {{-- Screenshot placeholder for settings panel --}}
                        <div class="mb-4 p-3 bg-light border rounded text-center">
                            <img src="{{ asset('images/onboarding/widget-settings.png') }}" 
                                 alt="Widget customization settings in theme editor" 
                                 class="img-fluid rounded"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="display:none;" class="text-muted small">
                                <i class="fas fa-image me-1"></i>Screenshot: Widget settings panel in theme editor
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-cog text-primary me-2"></i>Available Settings:</h6>
                                        <ul class="small mb-0">
                                            <li><strong>Colors:</strong> Primary color, text color, background</li>
                                            <li><strong>Position:</strong> Bottom right, bottom left, custom</li>
                                            <li><strong>Size:</strong> Chat window dimensions</li>
                                            <li><strong>Messages:</strong> Welcome message, placeholder text</li>
                                            <li><strong>Behavior:</strong> Auto-open delay, minimized by default</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-wrench text-success me-2"></i>How to Customize:</h6>
                                        <ol class="small mb-0">
                                            <li>Open theme editor</li>
                                            <li>Click <strong>App embeds</strong> in left sidebar</li>
                                            <li>Click <strong>AI Chat Support</strong></li>
                                            <li>Settings panel appears on the right</li>
                                            <li>Adjust colors, text, position as needed</li>
                                            <li>Click <strong>Save</strong></li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info border-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tip:</strong> Changes in the theme editor take effect immediately. Use the preview to see how it looks before saving.
                        </div>
                        
                        <div class="text-center">
                            <a href="{{ $deepLink }}" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt me-2"></i>Open Theme Editor to Customize
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($currentStep == 4)
                    <!-- Step 4: Add Training Data -->
                    <div>
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <strong>4</strong>
                            </div>
                            <div>
                                <h3 class="mb-0">Train Your AI Assistant</h3>
                                <p class="text-muted mb-0">Add knowledge so it can help your customers</p>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning border-0 mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Without training data, the AI won't know how to answer customer questions about your products or policies.
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card h-100 border-primary">
                                    <div class="card-body text-center">
                                        <div class="fs-1 text-primary mb-3"><i class="fas fa-question-circle"></i></div>
                                        <h6 class="fw-bold">FAQs</h6>
                                        <p class="small text-muted mb-0">Common questions and answers about your products, shipping, returns</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-success">
                                    <div class="card-body text-center">
                                        <div class="fs-1 text-success mb-3"><i class="fas fa-info-circle"></i></div>
                                        <h6 class="fw-bold">Store Info</h6>
                                        <p class="small text-muted mb-0">Business hours, contact details, policies, warranty information</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-info">
                                    <div class="card-body text-center">
                                        <div class="fs-1 text-info mb-3"><i class="fas fa-boxes"></i></div>
                                        <h6 class="fw-bold">Products</h6>
                                        <p class="small text-muted mb-0">Product descriptions, specifications, usage instructions</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="fw-bold mb-3">How to Add Training Data:</h6>
                            <ol class="mb-0">
                                <li class="mb-2">Go to your <a href="{{ route('customer.dashboard') }}" target="_blank">dashboard</a></li>
                                <li class="mb-2">Click <strong>"Data Management"</strong> in the menu</li>
                                <li class="mb-2">Add FAQs, store information, or product details</li>
                                <li class="mb-2">The AI learns automatically and improves with more data</li>
                            </ol>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="{{ route('customer.dashboard') }}" target="_blank" class="btn btn-success">
                                <i class="fas fa-database me-2"></i>Add Training Data Now
                            </a>
                        </div>
                    </div>
                    @endif

                    @if($currentStep == 5)
                    <!-- Step 5: Complete -->
                    <div>
                        <div class="text-center mb-4">
                            <div class="fs-1 text-success mb-3"><i class="fas fa-check-circle"></i></div>
                            <h2 class="mb-3">🎉 Setup Complete!</h2>
                            <p class="lead text-muted">Your AI Chat Support is ready to help your customers</p>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card bg-success bg-opacity-10 border-success h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle text-success fs-3 mb-2"></i>
                                        <h6 class="fw-bold">Widget Enabled</h6>
                                        <p class="small text-muted mb-0">Live on your store</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success bg-opacity-10 border-success h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-palette text-success fs-3 mb-2"></i>
                                        <h6 class="fw-bold">Customized</h6>
                                        <p class="small text-muted mb-0">Matches your brand</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success bg-opacity-10 border-success h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-brain text-success fs-3 mb-2"></i>
                                        <h6 class="fw-bold">AI Trained</h6>
                                        <p class="small text-muted mb-0">Ready to assist</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info border-0 mb-4">
                            <h6 class="fw-bold mb-2"><i class="fas fa-lightbulb me-2"></i>Next Steps:</h6>
                            <ul class="mb-0 small">
                                <li>Visit your store to see the widget in action</li>
                                <li>Add more training data to improve AI responses</li>
                                <li>Monitor chat conversations in your dashboard</li>
                                <li>Adjust widget settings anytime in theme editor</li>
                            </ul>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button wire:click="completedSetup" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-check me-2"></i>
                                Go to Dashboard
                            </button>
                        </div>
                        
                        <div class="text-center mt-3">
                            <p class="text-muted small mb-0">
                                Need help? <a href="mailto:{{ config('mail.from.address') }}">Contact Support</a>
                            </p>
                        </div>
                    </div>
                    @endif

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between mt-5 pt-4 border-top">
                        @if($currentStep > 1)
                            <button wire:click="previousStep" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Previous
                            </button>
                        @else
                            <div></div>
                        @endif
                        
                        @if($currentStep < 5)
                            <button wire:click="nextStep" class="btn btn-primary">
                                Next<i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
