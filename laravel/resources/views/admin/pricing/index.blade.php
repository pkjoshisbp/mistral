@extends('layouts.app')

@section('title', 'Pricing Management')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Pricing Management</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Pricing Management</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- Subscription Plans Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i> Subscription Plans
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Monthly Price</th>
                                <th>Yearly Price</th>
                                <th>Token Cap</th>
                                <th>Status</th>
                                <th>Sort Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subscriptionPlans as $plan)
                                <tr>
                                    <td>
                                        <strong>{{ $plan->name }}</strong>
                                        <br><small class="text-muted">{{ $plan->slug }}</small>
                                    </td>
                                    <td>${{ number_format($plan->monthly_price, 2) }}</td>
                                    <td>${{ number_format($plan->yearly_price, 2) }}</td>
                                    <td>{{ $plan->formatted_token_cap ?? number_format($plan->token_cap_monthly) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $plan->is_active ? 'success' : 'secondary' }}">
                                            {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $plan->sort_order }}</td>
                                    <td>
                                        <a href="{{ route('admin.pricing.subscription-plans.edit', $plan->id) }}" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Credit Packages Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-coins"></i> Credit Packages (Never Expire)
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.pricing.credit-packages.create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add New Package
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>USD Price</th>
                                <th>INR Price</th>
                                <th>Tokens</th>
                                <th>Cost per 1M Tokens</th>
                                <th>Status</th>
                                <th>Sort Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($creditPackages as $package)
                                <tr>
                                    <td>
                                        <strong>{{ $package->name }}</strong>
                                        <br><small class="text-muted">{{ $package->slug }}</small>
                                    </td>
                                    <td>${{ number_format($package->usd_price, 2) }}</td>
                                    <td>₹{{ number_format($package->inr_price, 2) }}</td>
                                    <td>{{ $package->formatted_tokens }} tokens</td>
                                    <td>
                                        ${{ number_format(($package->usd_price / $package->tokens) * 1000000, 2) }}
                                        <br><small class="text-muted">₹{{ number_format(($package->inr_price / $package->tokens) * 1000000, 2) }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $package->is_active ? 'success' : 'secondary' }}">
                                            {{ $package->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $package->sort_order }}</td>
                                    <td>
                                        <a href="{{ route('admin.pricing.credit-packages.edit', $package->id) }}" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        No credit packages found. <a href="{{ route('admin.pricing.credit-packages.create') }}">Create one now</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pricing Comparison Info -->
        <div class="card mt-4 bg-light">
            <div class="card-body">
                <h5><i class="fas fa-info-circle"></i> Pricing Strategy Guidelines</h5>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Subscription Plans (Monthly/Yearly):</h6>
                        <ul>
                            <li><strong>Starter:</strong> $49/month for 2M tokens = $24.50 per 1M tokens</li>
                            <li><strong>Pro:</strong> $199/month for 10M tokens = $19.90 per 1M tokens</li>
                            <li><strong>PAYG:</strong> $5 for 100K tokens = $50 per 1M tokens</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Credit Package Strategy:</h6>
                        <ul>
                            <li>Should be <strong>20-40% more expensive</strong> than subscription rates</li>
                            <li>Never expire - premium for flexibility</li>
                            <li>Recommended: $60-80 per 1M tokens for credits</li>
                            <li>Use INR conversion rate: ~83x USD price</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
