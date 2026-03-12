@extends('layouts.app')

@section('title', 'Create Credit Package')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Create Credit Package</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.pricing.index') }}">Pricing Management</a></li>
                    <li class="breadcrumb-item active">Create Package</li>
                </ol>
            </div>
        </div>
    </div>
    @if ($errors->any())
        <div class="container-fluid">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">New Credit Package</h3>
                    </div>
                    <form action="{{ route('admin.pricing.credit-packages.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Package Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label for="slug">Slug</label>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}" required>
                                <small class="form-text text-muted">Lowercase, letters and dashes only (e.g., test-1k)</small>
                                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="usd_price">USD Price</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control @error('usd_price') is-invalid @enderror" id="usd_price" name="usd_price" value="{{ old('usd_price', '1.00') }}" required>
                                            @error('usd_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                                            <input type="number" step="0.01" class="form-control @error('inr_price') is-invalid @enderror" id="inr_price" name="inr_price" value="{{ old('inr_price', '85.00') }}" required>
                                            @error('inr_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <small class="form-text text-muted">Suggested: USD × 83</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tokens">Number of Tokens</label>
                                <input type="number" class="form-control @error('tokens') is-invalid @enderror" id="tokens" name="tokens" value="{{ old('tokens', 1000) }}" required>
                                <small class="form-text text-muted">1M = 1,000,000 tokens</small>
                                @error('tokens')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label for="credit_validity_months">Credit Validity</label>
                                <select class="form-control @error('credit_validity_months') is-invalid @enderror" id="credit_validity_months" name="credit_validity_months" required>
                                    @foreach($validityOptions as $months => $label)
                                        <option value="{{ $months }}" {{ (int) old('credit_validity_months', 12) === (int) $months ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Credits remain active for the selected period and can be carried forward on timely renewal.</small>
                                @error('credit_validity_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-group">
                                <label for="features">Features (one per line)</label>
                                <textarea class="form-control @error('features') is-invalid @enderror" id="features" name="features" rows="6">{{ old('features') }}</textarea>
                                @error('features')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sort_order">Sort Order</label>
                                        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', 999) }}" required>
                                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch mt-4">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Create Package</button>
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
                                USD: $<span id="usd_per_million">0.00</span><br>
                                INR: ₹<span id="inr_per_million">0.00</span>
                            </div>
                            <div class="mb-3" id="pricing-recommendation"></div>
                        </div>
                    </div>
                </div>
                <div class="card bg-warning mt-3">
                    <div class="card-header">
                        <h3 class="card-title">⚠️ Pricing Strategy</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Credit packages should be priced at a premium over subscriptions due to longer validity and carry-forward flexibility.</strong></p>
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
    const usdPerM = document.getElementById('usd_per_million');
    const inrPerM = document.getElementById('inr_per_million');
    const recommendationDiv = document.getElementById('pricing-recommendation');

    function updateDerived() {
        const usdPrice = parseFloat(usdInput.value) || 0;
        const inrPrice = parseFloat(inrInput.value) || 0;
        const tokens = Math.max(parseInt(tokensInput.value) || 1, 1);
        usdPerM.textContent = ((usdPrice / tokens) * 1000000).toFixed(2);
        inrPerM.textContent = ((inrPrice / tokens) * 1000000).toFixed(2);

        const costPer1M = (usdPrice / tokens) * 1000000;
        let status = '', colorClass = '';
        if (costPer1M < 50) { status = 'Too Low - Below PAYG rate'; colorClass = 'text-danger'; }
        else if (costPer1M < 60) { status = 'Low - Consider increasing'; colorClass = 'text-warning'; }
        else if (costPer1M <= 80) { status = 'Good - Appropriate premium'; colorClass = 'text-success'; }
        else { status = 'High - May reduce demand'; colorClass = 'text-info'; }
        recommendationDiv.innerHTML = `<strong>Pricing Status:</strong><br><span class="${colorClass}">${status}</span>`;
    }

    usdInput.addEventListener('input', function() {
        if (!isNaN(parseFloat(this.value))) { inrInput.value = (parseFloat(this.value) * 100).toFixed(0); }
        updateDerived();
    });
    inrInput.addEventListener('input', updateDerived);
    tokensInput.addEventListener('input', updateDerived);
    updateDerived();
});
</script>
@endsection
