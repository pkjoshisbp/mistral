@extends('layouts.app')

@section('title', 'Edit Subscription Plan')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Subscription Plan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.pricing.index') }}">Pricing Management</a></li>
                    <li class="breadcrumb-item active">Edit Plan</li>
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
                        <h3 class="card-title">Edit: {{ $plan->name }}</h3>
                    </div>
                    <form action="{{ route('admin.pricing.subscription-plans.update', $plan->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Plan Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $plan->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" 
                                          id="description" name="description" rows="3" required>{{ old('description', $plan->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="monthly_price">Monthly Price (USD)</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control @error('monthly_price') is-invalid @enderror" 
                                                   id="monthly_price" name="monthly_price" value="{{ old('monthly_price', $plan->monthly_price) }}" required>
                                            @error('monthly_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="yearly_price">Yearly Price (USD)</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control @error('yearly_price') is-invalid @enderror" 
                                                   id="yearly_price" name="yearly_price" value="{{ old('yearly_price', $plan->yearly_price) }}" required>
                                            @error('yearly_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="token_cap_monthly">Monthly Token Cap</label>
                                        <input type="number" class="form-control @error('token_cap_monthly') is-invalid @enderror" 
                                               id="token_cap_monthly" name="token_cap_monthly" value="{{ old('token_cap_monthly', $plan->token_cap_monthly) }}" required>
                                        <small class="form-text text-muted">Set to 0 for unlimited tokens</small>
                                        @error('token_cap_monthly')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="overage_price_per_100k">Overage Price per 100K Tokens (USD)</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control @error('overage_price_per_100k') is-invalid @enderror" 
                                                   id="overage_price_per_100k" name="overage_price_per_100k" value="{{ old('overage_price_per_100k', $plan->overage_price_per_100k) }}" required>
                                            @error('overage_price_per_100k')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="features">Features (one per line)</label>
                                <textarea class="form-control @error('features') is-invalid @enderror" 
                                          id="features" name="features" rows="6">{{ old('features', implode("\n", $plan->features ?? [])) }}</textarea>
                                @error('features')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sort_order">Sort Order</label>
                                        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" 
                                               id="sort_order" name="sort_order" value="{{ old('sort_order', $plan->sort_order) }}" required>
                                        @error('sort_order')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch mt-4">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                                   value="1" {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_active">Active</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update Plan</button>
                            <a href="{{ route('admin.pricing.index') }}" class="btn btn-default">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-header">
                        <h3 class="card-title">Pricing Calculator</h3>
                    </div>
                    <div class="card-body">
                        <div class="pricing-info">
                            <div class="mb-3">
                                <strong>Current Cost per 1M Tokens:</strong><br>
                                <span class="text-primary h5">
                                    ${{ number_format(($plan->monthly_price / max($plan->token_cap_monthly, 1)) * 1000000, 2) }}
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Yearly Savings:</strong><br>
                                <span class="text-success">
                                    ${{ number_format(($plan->monthly_price * 12) - $plan->yearly_price, 2) }}
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>INR Equivalent (100x):</strong><br>
                                Monthly: ₹{{ number_format($plan->monthly_price * 83, 0) }}<br>
                                Yearly: ₹{{ number_format($plan->yearly_price * 83, 0) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
