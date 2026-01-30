<div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-robot me-2"></i>
                        Organization AI Model Management
                    </h4>
                    <small class="text-muted">Assign different AI models to organizations for customized performance</small>
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
                        <!-- Organizations List -->
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-building me-1"></i>
                                Organizations
                            </h6>
                            
                            <div class="list-group">
                                @foreach($organizations as $org)
                                    <button 
                                        type="button" 
                                        class="list-group-item list-group-item-action {{ $selectedOrganization && $selectedOrganization->id === $org->id ? 'active' : '' }}"
                                        wire:click="selectOrganization({{ $org->id }})">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $org->name }}</h6>
                                            @php
                                                $orgSettings = $org->settings ?? [];
                                                $hasCustomModel = isset($orgSettings['ai_model']);
                                            @endphp
                                            @if($hasCustomModel)
                                                <small class="text-success">
                                                    <i class="fas fa-cog"></i> Custom
                                                </small>
                                            @else
                                                <small class="text-muted">
                                                    <i class="fas fa-globe"></i> Global
                                                </small>
                                            @endif
                                        </div>
                                        <small class="text-muted">{{ $org->slug }}</small>
                                        @if($hasCustomModel)
                                            <br><small class="badge bg-primary">{{ $orgSettings['ai_model'] ?? 'N/A' }}</small>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- AI Settings Panel -->
                        <div class="col-md-8">
                            @if($showSettings && $selectedOrganization)
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-robot me-2"></i>
                                            AI Model Settings: {{ $selectedOrganization->name }}
                                        </h6>
                                    </div>
                                    
                                    <div class="card-body">
                                        <form wire:submit.prevent="saveAiSettings">
                                            <!-- AI Backend Type -->
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold">
                                                        <i class="fas fa-server me-1"></i>
                                                        AI Backend Type
                                                    </label>
                                                    <select wire:model.live="aiBackendType" class="form-select @error('aiBackendType') is-invalid @enderror">
                                                        <option value="ollama">Ollama (Recommended)</option>
                                                        <option value="llamacpp">Llama.cpp (Advanced)</option>
                                                    </select>
                                                    @error('aiBackendType')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold">
                                                        <i class="fas fa-building me-1"></i>
                                                        Model Provider
                                                    </label>
                                                    <select wire:model.live="aiModelProvider" class="form-select @error('aiModelProvider') is-invalid @enderror">
                                                        <option value="llama">Llama Models</option>
                                                        <option value="openai">OpenAI (Future)</option>
                                                    </select>
                                                    @error('aiModelProvider')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <!-- AI Model Selection -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">
                                                    <i class="fas fa-brain me-1"></i>
                                                    AI Model
                                                </label>
                                                <select wire:model="aiModel" class="form-select @error('aiModel') is-invalid @enderror">
                                                    <option value="">Select AI Model</option>
                                                    @foreach($this->getModelsForCurrentProvider() as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @error('aiModel')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">
                                                    Choose the AI model for this organization. Different models have different performance characteristics.
                                                </small>
                                            </div>

                                            <!-- Assistant Display Name -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">
                                                    <i class="fas fa-id-badge me-1"></i>
                Assistant Display Name
                                                </label>
                                                <input type="text" wire:model.defer="assistantDisplayName" class="form-control @error('assistantDisplayName') is-invalid @enderror" placeholder="e.g., Ava, Helper Bot, Support Assistant">
                                                @error('assistantDisplayName')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">Shown to users instead of the generic 'AI Assistant' across chat widgets and messaging. Leave blank to use default.</small>
                                            </div>

                                            <!-- Organization Type & Intent Keywords -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <strong>
                                                        <i class="fas fa-tags me-1"></i>
                                                        Organization Type & Intent Keywords
                                                    </strong>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-8">
                                                            <label class="form-label fw-bold">Organization Type</label>
                                                            <select wire:model.live="orgType" class="form-select">
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
                                                            <small class="text-muted">Used to prefill intent keywords for better accuracy. You can edit keywords after applying.</small>
                                                        </div>
                                                        <div class="col-md-4 d-flex align-items-end">
                                                            <button type="button" class="btn btn-outline-primary w-100" wire:click="applyIntentTemplate">
                                                                <i class="fas fa-magic me-1"></i>
                                                                Apply Template
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Booking Keywords</label>
                                                            <small class="text-muted d-block mb-1">Appointment requests, scheduling, reservations.</small>
                                                            <textarea wire:model.defer="intentKeywords.booking" class="form-control" rows="2" placeholder="appointment, reservation, schedule"></textarea>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Pricing Keywords</label>
                                                            <small class="text-muted d-block mb-1">Costs, fees, discounts, quotes.</small>
                                                            <textarea wire:model.defer="intentKeywords.pricing" class="form-control" rows="2" placeholder="price, fees, cost, discount"></textarea>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Realtime Data Keywords</label>
                                                            <small class="text-muted d-block mb-1">Availability, stock, live status, hours right now.</small>
                                                            <textarea wire:model.defer="intentKeywords.realtime_data" class="form-control" rows="2" placeholder="availability, stock, status"></textarea>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label fw-bold">Lookup Keywords</label>
                                                            <small class="text-muted d-block mb-1">Find/search/list items or services.</small>
                                                            <textarea wire:model.defer="intentKeywords.lookup" class="form-control" rows="2" placeholder="search, find, list"></textarea>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="form-label fw-bold">Static Info Keywords</label>
                                                            <small class="text-muted d-block mb-1">FAQs, policies, general info, contact, how-to.</small>
                                                            <textarea wire:model.defer="intentKeywords.static_info" class="form-control" rows="2" placeholder="policy, refund, warranty, rules"></textarea>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">Comma-separated keywords. These are added to global rules for this organization. Use common user phrases for best results.</small>
                                                </div>
                                            </div>

                                            <!-- Chat Email Notifications -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <strong>
                                                        <i class="fas fa-envelope me-1"></i>
                                                        Chat Email Notifications
                                                    </strong>
                                                </div>
                                                <div class="card-body">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" wire:model="notifyChatEmailEnabled" id="notifyChatEmailEnabled">
                                                        <label class="form-check-label" for="notifyChatEmailEnabled">Send each chat interaction by email</label>
                                                    </div>
                                                    <label class="form-label fw-bold">Notification Emails</label>
                                                    <textarea wire:model.defer="notifyChatEmails" class="form-control" rows="2" placeholder="owner@example.com, support@example.com"></textarea>
                                                    <small class="text-muted">Only sent when enabled. Use comma-separated emails.</small>
                                                </div>
                                            </div>

                                            <!-- Lead Notifications -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <strong>
                                                        <i class="fas fa-bullseye me-1"></i>
                                                        Lead Notifications
                                                    </strong>
                                                </div>
                                                <div class="card-body">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" wire:model="leadNotifyEnabled" id="leadNotifyEnabled">
                                                        <label class="form-check-label" for="leadNotifyEnabled">Notify when a lead is captured</label>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label fw-bold">Lead Notification Emails</label>
                                                        <textarea wire:model.defer="leadNotifyEmails" class="form-control" rows="2" placeholder="sales@example.com, owner@example.com"></textarea>
                                                        <small class="text-muted">Comma-separated emails.</small>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label fw-bold">Webhook URL (optional)</label>
                                                        <input type="url" wire:model.defer="leadNotifyWebhookUrl" class="form-control" placeholder="https://example.com/webhook">
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" wire:model="leadNotifyQualifiedOnly" id="leadNotifyQualifiedOnly">
                                                        <label class="form-check-label" for="leadNotifyQualifiedOnly">Only notify when lead is qualified (booking/pricing)</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Business Hours & Holidays -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <strong>
                                                        <i class="fas fa-clock me-1"></i>
                                                        Business Hours & Holidays
                                                    </strong>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-2">
                                                        <label class="form-label fw-bold">Business Hours</label>
                                                        <textarea wire:model.defer="businessHours" class="form-control" rows="2" placeholder="Mon-Fri: 9:00 AM - 6:00 PM; Sat: 10:00 AM - 4:00 PM"></textarea>
                                                        <small class="text-muted">Used for time-aware answers (open/closed questions).</small>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label fw-bold">Holiday Dates</label>
                                                        <textarea wire:model.defer="holidayDates" class="form-control" rows="2" placeholder="2026-01-26|Republic Day, 2026-03-29|Spring Break"></textarea>
                                                        <small class="text-muted">Comma-separated dates. Use YYYY-MM-DD with optional label after |.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Intent Classification Settings -->
                                            <div class="card mb-3">
                                                <div class="card-header">
                                                    <strong>
                                                        <i class="fas fa-route me-1"></i>
                                                        Intent Classification
                                                    </strong>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Strategy</label>
                                                            <select wire:model.live="intentStrategy" class="form-select @error('intentStrategy') is-invalid @enderror">
                                                                <option value="rules_only">Rules Only (fastest)</option>
                                                                <option value="rules_then_embedding">Rules → Embeddings</option>
                                                                <option value="rules_then_llm">Rules → LLM</option>
                                                                <option value="hybrid">Rules → Embeddings → LLM (best accuracy)</option>
                                                            </select>
                                                            @error('intentStrategy')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-bold">Use LLM Fallback</label>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" wire:model="intentUseLlm" id="intentUseLlm">
                                                                <label class="form-check-label" for="intentUseLlm">Enable LLM if needed</label>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">Rule Threshold</label>
                                                            <input type="number" step="0.01" min="0" max="1" wire:model="intentRuleThreshold" class="form-control @error('intentRuleThreshold') is-invalid @enderror">
                                                            @error('intentRuleThreshold')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">Embedding Threshold</label>
                                                            <input type="number" step="0.01" min="0" max="1" wire:model="intentEmbeddingThreshold" class="form-control @error('intentEmbeddingThreshold') is-invalid @enderror">
                                                            @error('intentEmbeddingThreshold')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">LLM Max Tokens</label>
                                                            <input type="number" min="16" max="256" wire:model="intentLlmMaxTokens" class="form-control @error('intentLlmMaxTokens') is-invalid @enderror">
                                                            @error('intentLlmMaxTokens')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="row mb-2">
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">LLM Temperature</label>
                                                            <input type="number" step="0.01" min="0" max="1" wire:model="intentLlmTemperature" class="form-control @error('intentLlmTemperature') is-invalid @enderror">
                                                            @error('intentLlmTemperature')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">LLM Top P</label>
                                                            <input type="number" step="0.01" min="0" max="1" wire:model="intentLlmTopP" class="form-control @error('intentLlmTopP') is-invalid @enderror">
                                                            @error('intentLlmTopP')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-bold">Repeat Penalty</label>
                                                            <input type="number" step="0.01" min="0.8" max="1.5" wire:model="intentLlmRepeatPenalty" class="form-control @error('intentLlmRepeatPenalty') is-invalid @enderror">
                                                            @error('intentLlmRepeatPenalty')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="mt-2">
                                                        <label class="form-label fw-bold">Intent LLM Model</label>
                                                        <input type="text" wire:model="intentLlmModel" class="form-control @error('intentLlmModel') is-invalid @enderror" placeholder="e.g., llama3.2:1b">
                                                        @error('intentLlmModel')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                        <small class="form-text text-muted">Used only for intent classification, not for full responses.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Model Information -->
                                            @if($aiModel)
                                                <div class="alert alert-info">
                                                    <h6 class="fw-bold mb-2">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Model Information
                                                    </h6>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <strong>Backend:</strong> {{ ucfirst($aiBackendType) }}
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Provider:</strong> {{ ucfirst($aiModelProvider) }}
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Model:</strong> {{ $aiModel }}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-1"></i>
                                                    Save AI Settings
                                                </button>
                                                
                                                <button type="button" wire:click="resetToGlobal" class="btn btn-outline-secondary">
                                                    <i class="fas fa-undo me-1"></i>
                                                    Reset to Global
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Current Global Settings Info -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-globe me-1"></i>
                                            Global AI Settings (Default)
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Backend:</strong> {{ \App\Models\AdminSetting::get('ai_backend_type', 'ollama') }}
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Provider:</strong> {{ \App\Models\AdminSetting::get('ai_model_provider', 'llama') }}
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Model:</strong> {{ \App\Models\AdminSetting::get('ai_model', 'llama3.2:3b') }}
                                            </div>
                                        </div>
                                        <small class="text-muted">Organizations without custom settings will use these defaults.</small>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Select an Organization</h5>
                                    <p class="text-muted">Choose an organization from the list to configure AI model settings</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>