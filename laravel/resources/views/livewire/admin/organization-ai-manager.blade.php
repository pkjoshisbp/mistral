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