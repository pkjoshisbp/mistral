<div>
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-building mr-2"></i>
                {{ $organization ? 'Edit Organization' : 'Create Organization' }}
            </h3>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="save">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.live="name" placeholder="Organization Name">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Slug *</label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" wire:model="slug" {{ $organization ? 'readonly' : '' }}>
                            @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted">Used for API endpoints</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" class="form-control @error('website') is-invalid @enderror" wire:model="website" placeholder="https://example.com">
                            @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Timezone *</label>
                            <select class="form-control @error('timezone') is-invalid @enderror" wire:model="timezone">
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New_York</option>
                                <option value="Europe/London">Europe/London</option>
                                <option value="Asia/Kolkata">Asia/Kolkata</option>
                                <option value="Asia/Singapore">Asia/Singapore</option>
                            </select>
                            @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control @error('contact_email') is-invalid @enderror" wire:model="contact_email" placeholder="support@example.com">
                            @error('contact_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" class="form-control @error('contact_phone') is-invalid @enderror" wire:model="contact_phone" placeholder="+1 555-123-4567">
                            @error('contact_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" rows="3" wire:model="description" placeholder="Short description"></textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="border rounded p-3 mb-3 bg-light">
                    <h6 class="mb-2"><i class="fas fa-question-circle"></i> FAQ Follow-up Settings</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="faq_follow_up_enabled" wire:model="faq_follow_up_enabled">
                        <label class="form-check-label" for="faq_follow_up_enabled">
                            Ask a follow-up question after FAQ answers
                        </label>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Follow-up text</label>
                        <input type="text" class="form-control @error('faq_follow_up_text') is-invalid @enderror" wire:model="faq_follow_up_text" placeholder="Would you like to know more about this?">
                        @error('faq_follow_up_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted d-block mt-1">Supports placeholders like <strong>@{{location}}</strong>, <strong>@{{location_contact}}</strong>, <strong>@{{country}}</strong>, and custom keys like <strong>@{{support_hours}}</strong>.</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Negative response keywords (skip follow-up)</label>
                        <textarea class="form-control @error('faq_follow_up_negative_keywords') is-invalid @enderror" rows="3" wire:model="faq_follow_up_negative_keywords" placeholder="no, no thanks, not interested, stop"></textarea>
                        @error('faq_follow_up_negative_keywords') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Comma or new-line separated keywords. If a response contains any of these, the follow-up is skipped.</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Dynamic variables (key|value)</label>
                        <textarea class="form-control @error('faq_follow_up_dynamic_variables') is-invalid @enderror" rows="3" wire:model="faq_follow_up_dynamic_variables" placeholder="support_hours|Mon-Sat 9AM-6PM&#10;default_contact|+91 7000000000"></textarea>
                        @error('faq_follow_up_dynamic_variables') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Use one pair per line. These can be used as placeholders in follow-up text, e.g. <strong>@{{support_hours}}</strong>.</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label">Location contacts (location|contact)</label>
                        <textarea class="form-control @error('faq_follow_up_location_contacts') is-invalid @enderror" rows="4" wire:model="faq_follow_up_location_contacts" placeholder="sambalpur|+91 7381015933&#10;angul|+91 7381015934"></textarea>
                        @error('faq_follow_up_location_contacts') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">When visitor location is available, <strong>@{{location_contact}}</strong> is auto-filled from this map.</small>
                    </div>
                    <hr>
                    <div class="border rounded p-3 mb-3 bg-white">
                        <h6 class="mb-2"><i class="fas fa-sliders-h"></i> Widget Follow-up Rule Policy</h6>
                        <small class="text-muted d-block mb-2">These rules apply only to this organization and override default behavior.</small>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="widget_skip_intent_on_affirmative_follow_up" wire:model="widget_skip_intent_on_affirmative_follow_up">
                            <label class="form-check-label" for="widget_skip_intent_on_affirmative_follow_up">
                                Skip intent detection for affirmative follow-up (e.g., "yes please")
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="widget_skip_exact_match_on_affirmative_follow_up" wire:model="widget_skip_exact_match_on_affirmative_follow_up">
                            <label class="form-check-label" for="widget_skip_exact_match_on_affirmative_follow_up">
                                Skip exact-match shortcut on affirmative follow-up
                            </label>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label">Affirmative follow-up max tokens</label>
                            <input type="number" min="80" max="300" class="form-control @error('widget_affirmative_follow_up_max_tokens') is-invalid @enderror" wire:model="widget_affirmative_follow_up_max_tokens">
                            @error('widget_affirmative_follow_up_max_tokens') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group mt-3 mb-0">
                            <label class="form-label">Custom Widget Starter Prompts</label>
                            <textarea class="form-control @error('widget_custom_starter_prompts') is-invalid @enderror" rows="3" wire:model="widget_custom_starter_prompts" placeholder="What services do you offer?&#10;What are your pricing options?&#10;What documents are required?"></textarea>
                            @error('widget_custom_starter_prompts') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted d-block mt-1">One prompt per line. These appear as clickable chips in the widget. You can mix these with FAQs marked as starter prompts.</small>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">Supplementary context instruction (LLM)</label>
                        <small class="text-muted d-block mb-2">This instruction stays active for all responses and tells the model how to use supplementary context from Qdrant.</small>
                        <textarea class="form-control @error('supplementary_instruction') is-invalid @enderror" rows="4" wire:model="supplementary_instruction" placeholder="Enter your supplementary context instruction..."></textarea>
                        @error('supplementary_instruction') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted d-block mt-2">Example: If context contains 'Supplementary:', add one short final sentence like 'For more details, contact &lt;location&gt; at &lt;number&gt;.' Only use values present in context.</small>
                        <small class="text-muted d-block">Tip: Keep it generic so it works for location, pricing, policy, support, or any other dataset.</small>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        @if($organization)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Not Created</span>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ $organization ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(!$organization)
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> You can create one organization which will be linked to your account.
        </div>
    @endif
</div>
