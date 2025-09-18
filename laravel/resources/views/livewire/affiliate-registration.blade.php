<div class="container py-5" style="max-width: 1080px;">
    @if($showSuccess)
        <div class="alert alert-success d-flex align-items-start gap-3">
            <div class="fs-4">✅</div>
            <div>
                <h5 class="mb-1">Application Submitted Successfully!</h5>
                <p class="mb-0 small">Thank you for applying to become an affiliate partner. We'll review your application and get back to you within 2-3 business days. You'll receive login credentials via email once approved.</p>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="alert alert-danger">{{ $errorMessage }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white py-4">
            <h1 class="h3 mb-1">Become an Affiliate Partner</h1>
            <p class="mb-0 small text-white-50">Join our affiliate program and earn commissions by promoting our AI chat solutions</p>
        </div>
        <div class="card-body bg-light border-bottom">
            <h2 class="h5 mb-4">Choose Your Commission Structure</h2>
            <div class="row g-3">
                @foreach($commission_rates as $type => $info)
                    <div class="col-md-6">
                        <label class="w-100 h-100">
                            <input type="radio" wire:model="commission_type" value="{{ $type }}" class="d-none">
                            <div class="border rounded p-3 h-100 commission-option {{ $commission_type === $type ? 'border-primary bg-white shadow-sm' : 'bg-white' }}" style="cursor:pointer; transition: .15s;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="h6 mb-0">{{ ucwords(str_replace('-', ' ', $type)) }}</h3>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold">{{ $info['rate'] }}</span>
                                </div>
                                <p class="small text-muted mb-2">{{ $info['description'] }}</p>
                                <div class="small text-success d-flex align-items-center gap-1">
                                    <span>✔</span>
                                    <span>{{ $info['benefit'] }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
        <form wire:submit.prevent="submit">
            <div class="card-body">
                <h2 class="h5 mb-3">Your Information</h2>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address *</label>
                        <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Password *</label>
                        <input type="password" wire:model="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="form-control">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" wire:model="phone" class="form-control @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Company / Organization</label>
                        <input type="text" wire:model="company" class="form-control">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Website / Blog URL</label>
                    <input type="url" wire:model="website" placeholder="https://example.com" class="form-control @error('website') is-invalid @enderror">
                    @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <h2 class="h5 mb-3">Marketing Information</h2>
                <div class="mb-3">
                    <label class="form-label">Why do you want to become an affiliate? *</label>
                    <textarea wire:model="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="Tell us about your motivation, goals, and how you plan to promote our services..."></textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Marketing Experience *</label>
                        <textarea wire:model="experience" rows="3" class="form-control @error('experience') is-invalid @enderror" placeholder="Describe your marketing experience..."></textarea>
                        @error('experience') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Marketing Channels *</label>
                        <textarea wire:model="marketing_channels" rows="3" class="form-control @error('marketing_channels') is-invalid @enderror" placeholder="Social media, email, blog, paid ads, etc..."></textarea>
                        @error('marketing_channels') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Estimated Monthly Reach / Traffic *</label>
                    <input type="text" wire:model="monthly_traffic" class="form-control @error('monthly_traffic') is-invalid @enderror" placeholder="e.g., 10k website visitors, 5k email subscribers, 50k social media followers">
                    @error('monthly_traffic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-check bg-white p-3 rounded border mb-4">
                    <input class="form-check-input" type="checkbox" wire:model="terms_accepted" id="termsCheck">
                    <label class="form-check-label small" for="termsCheck">
                        I agree to the <a href="#" class="text-decoration-underline">Terms and Conditions</a> and
                        <a href="#" class="text-decoration-underline">Affiliate Agreement</a>. I understand that my application will be reviewed and I will be notified of the decision within 2-3 business days.
                    </label>
                    @error('terms_accepted') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-5" wire:loading.attr="disabled">
                        <span wire:loading.remove>Submit Application</span>
                        <span wire:loading>Processing...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    <style>
        .commission-option:hover { border-color: var(--bs-primary); }
        .commission-option input:checked + .commission-option { border-color: var(--bs-primary); }
    </style>
</div>