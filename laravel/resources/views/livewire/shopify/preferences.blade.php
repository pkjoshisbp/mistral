<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            @if($errorMessage)
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    {{ $errorMessage }}
                </div>
            @endif

            @if($successMessage)
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ $successMessage }}
                </div>
            @endif

            @if($organization)
                <!-- Header -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="mb-0">
                            <i class="fas fa-cog text-primary me-2"></i>
                            AI Chat Widget Settings
                        </h2>
                        <p class="text-muted mb-0 mt-2">
                            <i class="fas fa-store me-1"></i>
                            Store: <strong>{{ $shop }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Settings Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-paint-brush me-2"></i>
                            Widget Appearance & Behavior
                        </h5>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="savePreferences">
                            
                            <!-- Widget Enabled -->
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input 
                                        class="form-check-input" 
                                        type="checkbox" 
                                        id="widget_enabled" 
                                        wire:model="widget_enabled"
                                        style="width: 3rem; height: 1.5rem;"
                                    >
                                    <label class="form-check-label" for="widget_enabled">
                                        <strong>Enable AI Chat Widget</strong>
                                        <small class="d-block text-muted">
                                            Turn the widget on or off on your storefront
                                        </small>
                                    </label>
                                </div>
                            </div>

                            <hr>

                            <!-- Widget Position -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-location-arrow me-1"></i>
                                    <strong>Widget Position</strong>
                                </label>
                                <select class="form-select" wire:model="widget_position">
                                    <option value="bottom-right">Bottom Right</option>
                                    <option value="bottom-left">Bottom Left</option>
                                    <option value="top-right">Top Right</option>
                                    <option value="top-left">Top Left</option>
                                </select>
                                @error('widget_position')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Primary Color -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-palette me-1"></i>
                                    <strong>Primary Color</strong>
                                </label>
                                <div class="input-group">
                                    <input 
                                        type="color" 
                                        class="form-control form-control-color" 
                                        wire:model="primary_color"
                                        style="width: 80px;"
                                    >
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        wire:model="primary_color"
                                        placeholder="#007bff"
                                    >
                                </div>
                                <small class="text-muted">
                                    Choose a color that matches your brand
                                </small>
                                @error('primary_color')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Welcome Message -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-message me-1"></i>
                                    <strong>Welcome Message</strong>
                                </label>
                                <textarea 
                                    class="form-control" 
                                    wire:model="welcome_message"
                                    rows="3"
                                    maxlength="200"
                                    placeholder="Hello! How can I help you today?"
                                ></textarea>
                                <small class="text-muted">
                                    {{ strlen($welcome_message) }}/200 characters
                                </small>
                                @error('welcome_message')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr>

                            <h6 class="mb-3">
                                <i class="fas fa-arrows-alt me-1"></i>
                                Widget Offset (pixels from edge)
                            </h6>

                            <div class="row">
                                <!-- Horizontal Offset -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <strong>Horizontal Offset</strong>
                                    </label>
                                    <input 
                                        type="number" 
                                        class="form-control" 
                                        wire:model="widget_offset_x"
                                        min="0"
                                        max="200"
                                    >
                                    <small class="text-muted">
                                        Distance from left/right edge
                                    </small>
                                    @error('widget_offset_x')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Vertical Offset -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <strong>Vertical Offset</strong>
                                    </label>
                                    <input 
                                        type="number" 
                                        class="form-control" 
                                        wire:model="widget_offset_y"
                                        min="0"
                                        max="200"
                                    >
                                    <small class="text-muted">
                                        Distance from top/bottom edge
                                    </small>
                                    @error('widget_offset_y')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr>

                            <!-- Submit Button -->
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>
                                    Save Settings
                                </button>
                                
                                <div wire:loading wire:target="savePreferences">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Saving...
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Preview Card -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-eye me-2"></i>
                            Widget Preview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Changes will be visible on your storefront immediately after saving. 
                            Please allow a few seconds for the widget to reload.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
    $wire.on('preferences-saved', () => {
        setTimeout(() => {
            $wire.set('successMessage', '');
        }, 3000);
    });
</script>
@endscript
