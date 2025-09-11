@extends('layouts.app')

@section('title', 'Edit Credit Package')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Credit Package</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.pricing.index') }}">Pricing Management</a></li>
                    <li class="breadcrumb-item active">Edit Package</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Edit: {{ $package->name }}</h3>
                    </div>
                    <form action="{{ route('admin.pricing.credit-packages.update', $package->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Package Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $package->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" 
                                          id="description" name="description" rows="3" required>{{ old('description', $package->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="usd_price">USD Price</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control @error('usd_price') is-invalid @enderror" 
                                                   id="usd_price" name="usd_price" value="{{ old('usd_price', $package->usd_price) }}" required>
                                            @error('usd_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="inr_price">INR Price</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₹</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control @error('inr_price') is-invalid @enderror" 
                                                   id="inr_price" name="inr_price" value="{{ old('inr_price', $package->inr_price) }}" required>
                                            @error('inr_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <small class="form-text text-muted">Suggested: USD × 83</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tokens">Number of Tokens</label>
                                <input type="number" class="form-control @error('tokens') is-invalid @enderror" 
                                       id="tokens" name="tokens" value="{{ old('tokens', $package->tokens) }}" required>
                                <small class="form-text text-muted">1M = 1,000,000 tokens</small>
                                @error('tokens')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="features">Features (one per line)</label>
                                <textarea class="form-control @error('features') is-invalid @enderror" 
                                          id="features" name="features" rows="6">{{ old('features', implode("\n", $package->features ?? [])) }}</textarea>
                                @error('features')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sort_order">Sort Order</label>
                                        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                               id="sort_order" name="sort_order" value="{{ old('sort_order', $package->sort_order) }}" required>
                                        @error('sort_order')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch mt-4">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                                   value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update Package</button>
                            <a href="{{ route('admin.pricing.index') }}" class="btn btn-default">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-header">
                        <h3 class="card-title">Pricing Analysis</h3>
                    </div>
                    <div class="card-body">
                        <div class="pricing-info">
                            <div class="mb-3">
                                <strong>Cost per 1M Tokens:</strong><br>
                                USD: ${{ number_format(($package->usd_price / max($package->tokens, 1)) * 1000000, 2) }}<br>
                                INR: ₹{{ number_format(($package->inr_price / max($package->tokens, 1)) * 1000000, 2) }}
                            </div>
                            
                            <div class="mb-3">
                                <strong>vs Subscription Plans:</strong><br>
                                <small class="text-muted">
                                    • Starter: $24.50 per 1M/month<br>
                                    • Pro: $19.90 per 1M/month<br>
                                    • PAYG: $50 per 1M
                                </small>
                            </div>

                            <div class="mb-3" id="pricing-recommendation">
                                <!-- Will be filled by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-warning mt-3">
                    <div class="card-header">
                        <h3 class="card-title">⚠️ Pricing Strategy</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Credit packages should be MORE expensive than subscriptions since they never expire.</strong></p>
                        <p>Recommended range: <strong>$60-80 per 1M tokens</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const usdInput = document.getElementById('usd_price');
    const inrInput = document.getElementById('inr_price');
    const tokensInput = document.getElementById('tokens');
    const recommendationDiv = document.getElementById('pricing-recommendation');

    function updateRecommendation() {
        const usdPrice = parseFloat(usdInput.value) || 0;
        const tokens = parseInt(tokensInput.value) || 1;
        const costPer1M = (usdPrice / tokens) * 1000000;
        
        let status = '';
        let colorClass = '';
        
        if (costPer1M < 50) {
            status = 'Too Low - Below PAYG rate';
            colorClass = 'text-danger';
        } else if (costPer1M >= 50 && costPer1M < 60) {
            status = 'Low - Consider increasing';
            colorClass = 'text-warning';
        } else if (costPer1M >= 60 && costPer1M <= 80) {
            status = 'Good - Appropriate premium';
            colorClass = 'text-success';
        } else {
            status = 'High - May reduce demand';
            colorClass = 'text-info';
        }
        
        recommendationDiv.innerHTML = `
            <strong>Pricing Status:</strong><br>
            <span class="${colorClass}">${status}</span>
        `;
    }

    // Auto-calculate INR when USD changes
    usdInput.addEventListener('input', function() {
        inrInput.value = (parseFloat(this.value) * 83).toFixed(0);
        updateRecommendation();
    });

    tokensInput.addEventListener('input', updateRecommendation);
    
    // Initial calculation
    updateRecommendation();
});
</script>
@endsection
