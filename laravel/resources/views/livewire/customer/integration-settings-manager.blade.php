<div>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Integration Settings</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-check-circle"></i> {{ session('message') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            @endif

            <div class="row">
                <!-- Organization Information -->
                <div class="col-lg-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-building"></i> Organization Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <form wire:submit.prevent="saveSettings">
                                <div class="form-group">
                                    <label for="name">Organization Name *</label>
                                    <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" id="name" placeholder="Your Organization Name">
                                    @error('name') 
                                        <span class="invalid-feedback">{{ $message }}</span> 
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" id="description" rows="3" placeholder="Brief description of your organization"></textarea>
                                    @error('description') 
                                        <span class="invalid-feedback">{{ $message }}</span> 
                                    @enderror
                                    <small class="form-text text-muted">This helps the AI understand your business better.</small>
                                </div>

                                <div class="form-group">
                                    <label for="website">Website URL</label>
                                    <input type="url" wire:model="website" class="form-control @error('website') is-invalid @enderror" id="website" placeholder="https://example.com">
                                    @error('website') 
                                        <span class="invalid-feedback">{{ $message }}</span> 
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="contact_email">Contact Email</label>
                                    <input type="email" wire:model="contact_email" class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" placeholder="support@example.com">
                                    @error('contact_email') 
                                        <span class="invalid-feedback">{{ $message }}</span> 
                                    @enderror
                                    <small class="form-text text-muted">Email address for customer inquiries.</small>
                                </div>

                                <div class="form-group">
                                    <label for="contact_phone">Contact Phone</label>
                                    <input type="text" wire:model="contact_phone" class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" placeholder="+1 555-123-4567">
                                    @error('contact_phone') 
                                        <span class="invalid-feedback">{{ $message }}</span> 
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="business_hours">Business Hours</label>
                                    <textarea wire:model="business_hours" class="form-control @error('business_hours') is-invalid @enderror" id="business_hours" rows="2" placeholder="Mon-Fri: 9:00 AM - 6:00 PM; Sat: 10:00 AM - 4:00 PM"></textarea>
                                    @error('business_hours')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Shown to the AI for time-aware answers. You can append a timezone (e.g., "IST" or "UTC+05:30").</small>
                                </div>

                                <div class="form-group">
                                    <label for="holiday_dates">Holiday Dates</label>
                                    <textarea wire:model="holiday_dates" class="form-control @error('holiday_dates') is-invalid @enderror" id="holiday_dates" rows="2" placeholder="2026-01-26|Republic Day, 2026-03-29|Spring Break"></textarea>
                                    @error('holiday_dates')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Comma-separated dates. Use YYYY-MM-DD with optional label after |.</small>
                                </div>

                                <div class="form-group">
                                    <label for="seasonal_promotions">Seasonal Promotions</label>
                                    <textarea wire:model="seasonal_promotions" class="form-control @error('seasonal_promotions') is-invalid @enderror" id="seasonal_promotions" rows="3" placeholder="2026-02-01 to 2026-02-10 | Winter Sale | 10% off all items"></textarea>
                                    @error('seasonal_promotions')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">One per line. Format: YYYY-MM-DD to YYYY-MM-DD | Title | Details.</small>
                                </div>

                                <div class="form-group">
                                    <label for="response_tone">Response Tone</label>
                                    <select wire:model="response_tone" class="form-control @error('response_tone') is-invalid @enderror" id="response_tone">
                                        <option value="friendly">Friendly</option>
                                        <option value="professional">Professional</option>
                                        <option value="concise">Concise</option>
                                    </select>
                                    @error('response_tone')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="response_language">Response Language</label>
                                    <select wire:model="response_language" class="form-control @error('response_language') is-invalid @enderror" id="response_language">
                                        <option value="auto">Auto-detect</option>
                                        <option value="en">English</option>
                                        <option value="hi">Hindi</option>
                                        <option value="es">Spanish</option>
                                        <option value="fr">French</option>
                                        <option value="de">German</option>
                                    </select>
                                    @error('response_language')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">Auto-detect matches the user's language.</small>
                                </div>

                                <div class="form-group">
                                    <label for="query_translation_map">Query Translation Map (optional)</label>
                                    <textarea wire:model.defer="query_translation_map" class="form-control @error('query_translation_map') is-invalid @enderror" id="query_translation_map" rows="5" placeholder="mehr infos = more information&#10;prix = price&#10;servicio = service"></textarea>
                                    @error('query_translation_map')
                                        <span class="invalid-feedback">{{ $message }}</span>
                                    @enderror
                                    <small class="form-text text-muted">One mapping per line in format <strong>source = target</strong> (or <strong>source =&gt; target</strong>). Add frequent user terms from any language to improve multilingual FAQ matching.</small>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="verified_only_mode" wire:model="verified_only_mode">
                                        <label class="custom-control-label" for="verified_only_mode">Verified-only answers (use KB/live data only)</label>
                                    </div>
                                    <small class="form-text text-muted">If enabled, the assistant only answers using verified sources.</small>
                                </div>

                                <div class="form-group">
                                    <label class="d-block">Guardrail Categories</label>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="guardrail_legal" wire:model="guardrail_categories" value="legal">
                                        <label class="custom-control-label" for="guardrail_legal">Legal</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="guardrail_medical" wire:model="guardrail_categories" value="medical">
                                        <label class="custom-control-label" for="guardrail_medical">Medical</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="guardrail_finance" wire:model="guardrail_categories" value="finance">
                                        <label class="custom-control-label" for="guardrail_finance">Finance</label>
                                    </div>
                                    <small class="form-text text-muted">If enabled, sensitive topics require approval to answer.</small>
                                </div>

                                <div class="form-group">
                                    <label class="d-block">Approved Sensitive Categories</label>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="approved_legal" wire:model="approved_sensitive_categories" value="legal">
                                        <label class="custom-control-label" for="approved_legal">Legal</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="approved_medical" wire:model="approved_sensitive_categories" value="medical">
                                        <label class="custom-control-label" for="approved_medical">Medical</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="approved_finance" wire:model="approved_sensitive_categories" value="finance">
                                        <label class="custom-control-label" for="approved_finance">Finance</label>
                                    </div>
                                    <small class="form-text text-muted">Only approved categories can be answered.</small>
                                </div>

                                @if($integration)
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Integration Type:</strong> 
                                        <span class="badge badge-primary ml-2">{{ strtoupper($integration->provider) }}</span>
                                        @if($integration->shop)
                                            <br><small class="text-muted">Shop: {{ $integration->shop }}</small>
                                        @endif
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Widget Settings -->
                <div class="col-lg-6">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-comments"></i> Chat Widget Settings
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="welcome_message">Welcome Message *</label>
                                <input type="text" wire:model="welcome_message" class="form-control @error('welcome_message') is-invalid @enderror" id="welcome_message" placeholder="Hello! How can I help you today?">
                                @error('welcome_message') 
                                    <span class="invalid-feedback">{{ $message }}</span> 
                                @enderror
                                <small class="form-text text-muted">First message users see when opening the chat.</small>
                            </div>

                            <div class="form-group">
                                <label for="widget_position">Widget Position *</label>
                                <select wire:model="widget_position" class="form-control @error('widget_position') is-invalid @enderror" id="widget_position">
                                    <option value="bottom-right">Bottom Right</option>
                                    <option value="bottom-left">Bottom Left</option>
                                    <option value="top-right">Top Right</option>
                                    <option value="top-left">Top Left</option>
                                </select>
                                @error('widget_position') 
                                    <span class="invalid-feedback">{{ $message }}</span> 
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="primary_color">Primary Color *</label>
                                <div class="input-group">
                                    <input type="color" wire:model="primary_color" class="form-control @error('primary_color') is-invalid @enderror" id="primary_color" style="max-width: 100px;">
                                    <input type="text" wire:model="primary_color" class="form-control ml-2" placeholder="#007bff">
                                </div>
                                @error('primary_color') 
                                    <span class="invalid-feedback">{{ $message }}</span> 
                                @enderror
                                <small class="form-text text-muted">Widget theme color.</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="widget_offset_x">Horizontal Offset (px)</label>
                                        <input type="number" wire:model="widget_offset_x" class="form-control @error('widget_offset_x') is-invalid @enderror" id="widget_offset_x" min="0" max="200">
                                        @error('widget_offset_x') 
                                            <span class="invalid-feedback">{{ $message }}</span> 
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="widget_offset_y">Vertical Offset (px)</label>
                                        <input type="number" wire:model="widget_offset_y" class="form-control @error('widget_offset_y') is-invalid @enderror" id="widget_offset_y" min="0" max="200">
                                        @error('widget_offset_y') 
                                            <span class="invalid-feedback">{{ $message }}</span> 
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="chat_history_ttl_hours">Chat History TTL (hours) *</label>
                                        <input type="number" wire:model="chat_history_ttl_hours" class="form-control @error('chat_history_ttl_hours') is-invalid @enderror" id="chat_history_ttl_hours" min="1" max="168">
                                        @error('chat_history_ttl_hours')
                                            <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                        <small class="form-text text-muted">How long to keep chat history in the browser (1–168 hours). Default 24.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-secondary">
                                <h6 class="alert-heading"><i class="fas fa-code"></i> Widget Preview</h6>
                                <div class="mt-2">
                                    <div class="d-inline-block px-3 py-2 rounded" style="background-color: {{ $primary_color }}; color: white;">
                                        <i class="fas fa-comments mr-2"></i>
                                        Chat with us
                                    </div>
                                </div>
                                <small class="d-block mt-2 text-muted">Position: {{ ucwords(str_replace('-', ' ', $widget_position)) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Intent Keywords & Org Type -->
                <div class="col-lg-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-tags"></i> Organization Type & Intent Keywords
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="org_type">Organization Type</label>
                                <div class="input-group">
                                    <select wire:model.live="org_type" class="form-control" id="org_type">
                                        <option value="">Select type</option>
                                        <option value="ecommerce">E-commerce Site</option>
                                        <option value="hospital">Hospital</option>
                                        <option value="clinic">Clinic</option>
                                        <option value="automobile_dealer">Automobile Dealer</option>
                                        <option value="ngo">NGO</option>
                                        <option value="school">School</option>
                                        <option value="college">College</option>
                                        <option value="restaurant">Restaurant</option>
                                        <option value="real_estate">Real Estate</option>
                                        <option value="travel">Travel</option>
                                        <option value="fitness">Fitness/Gym</option>
                                        <option value="logistics">Logistics</option>
                                        <option value="fintech">Fintech</option>
                                        <option value="real_estate_rental">Real Estate (Rentals)</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" wire:click="applyIntentTemplate">
                                            <i class="fas fa-magic"></i> Apply Template
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Prefill keywords for your industry, then edit as needed.</small>
                            </div>

                            <div class="form-group">
                                <label>Booking Keywords</label>
                                <small class="form-text text-muted">Appointment requests, scheduling, reservations.</small>
                                <textarea wire:model.defer="intent_keywords.booking" class="form-control" rows="2" placeholder="appointment, reservation, schedule"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Pricing Keywords</label>
                                <small class="form-text text-muted">Costs, fees, discounts, quotes.</small>
                                <textarea wire:model.defer="intent_keywords.pricing" class="form-control" rows="2" placeholder="price, fees, cost, discount"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Realtime Data Keywords</label>
                                <small class="form-text text-muted">Availability, stock, live status, hours right now.</small>
                                <textarea wire:model.defer="intent_keywords.realtime_data" class="form-control" rows="2" placeholder="availability, stock, status"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Lookup Keywords</label>
                                <small class="form-text text-muted">Find/search/list items or services.</small>
                                <textarea wire:model.defer="intent_keywords.lookup" class="form-control" rows="2" placeholder="search, find, list"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Static Info Keywords</label>
                                <small class="form-text text-muted">FAQs, policies, general info, contact, how-to.</small>
                                <textarea wire:model.defer="intent_keywords.static_info" class="form-control" rows="2" placeholder="policy, refund, warranty, rules"></textarea>
                            </div>
                            <small class="form-text text-muted">Comma-separated keywords. These are added to global rules for your organization.</small>
                        </div>
                    </div>
                </div>

                <!-- Chat Email Notifications -->
                <div class="col-lg-6">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-envelope"></i> Chat Email Notifications
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="notify_chat_email_enabled" wire:model="notify_chat_email_enabled">
                                    <label class="custom-control-label" for="notify_chat_email_enabled">Enable chat email notifications</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="notify_chat_email_mode">Email Frequency</label>
                                <select id="notify_chat_email_mode" class="form-control" wire:model="notify_chat_email_mode">
                                    <option value="immediate">Send every interaction</option>
                                    <option value="digest">Send digest every few minutes</option>
                                </select>
                                <small class="form-text text-muted">Digest mode groups multiple messages into a single email.</small>
                            </div>
                            <div class="form-group">
                                <label for="notify_chat_email_interval_minutes">Digest Interval (minutes)</label>
                                <input type="number" min="1" max="120" class="form-control" id="notify_chat_email_interval_minutes" wire:model="notify_chat_email_interval_minutes">
                                <small class="form-text text-muted">Used only for digest mode. Recommended: 10-15 minutes.</small>
                            </div>
                            <div class="form-group">
                                <label for="notify_chat_emails">Notification Emails</label>
                                <textarea wire:model.defer="notify_chat_emails" class="form-control" id="notify_chat_emails" rows="2" placeholder="owner@example.com, support@example.com"></textarea>
                                <small class="form-text text-muted">Only sent when enabled. Use comma-separated emails.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-headset"></i> Human Handoff
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="agent_availability">Agent Availability</label>
                                <select id="agent_availability" class="form-control" wire:model="agent_availability">
                                    <option value="auto">Auto (use business hours)</option>
                                    <option value="online">Always Online</option>
                                    <option value="offline">Always Offline</option>
                                </select>
                                <small class="form-text text-muted">When set to Auto, handoff is shown only during business hours if defined.</small>
                            </div>
                            <div class="form-group">
                                <label for="handoff_offline_message">Offline Message</label>
                                <textarea wire:model.defer="handoff_offline_message" class="form-control" id="handoff_offline_message" rows="2" placeholder="Our agents are currently offline. Please leave your contact details or reach us via the options below."></textarea>
                                <small class="form-text text-muted">Shown when agents are offline. Contact channels are appended automatically.</small>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="escalation_notify_enabled" wire:model="escalation_notify_enabled">
                                    <label class="custom-control-label" for="escalation_notify_enabled">Send email when escalation happens</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="escalation_notify_emails">Escalation Notification Emails</label>
                                <textarea wire:model.defer="escalation_notify_emails" class="form-control" id="escalation_notify_emails" rows="2" placeholder="support@example.com, owner@example.com"></textarea>
                                <small class="form-text text-muted">Comma-separated emails.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bullseye"></i> Lead Notifications
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="lead_notify_enabled" wire:model="lead_notify_enabled">
                                    <label class="custom-control-label" for="lead_notify_enabled">Notify when a lead is captured</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="lead_notify_emails">Lead Notification Emails</label>
                                <textarea wire:model.defer="lead_notify_emails" class="form-control" id="lead_notify_emails" rows="2" placeholder="sales@example.com, owner@example.com"></textarea>
                                <small class="form-text text-muted">Comma-separated emails.</small>
                            </div>
                            <div class="form-group">
                                <label for="lead_notify_webhook_url">Webhook URL (optional)</label>
                                <input type="url" wire:model.defer="lead_notify_webhook_url" class="form-control" id="lead_notify_webhook_url" placeholder="https://example.com/webhook">
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="lead_notify_qualified_only" wire:model="lead_notify_qualified_only">
                                    <label class="custom-control-label" for="lead_notify_qualified_only">Only notify when lead is qualified (booking/pricing)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <button wire:click="saveSettings" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save All Settings
                            </button>
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-secondary btn-lg ml-2">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
