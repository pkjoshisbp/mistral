<div>
    <div class="row">
        <!-- Current Subscription Card -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-credit-card"></i>
                        Current Subscription
                    </h4>
                </div>
                <div class="card-body">
                    @if($currentSubscription)
                        <div class="row">
                            <div class="col-md-6">
                                <h5>{{ $currentSubscription->subscriptionPlan->name }}</h5>
                                <p class="text-muted">{{ $currentSubscription->subscriptionPlan->description }}</p>
                                
                                <div class="mb-3">
                                    <strong>Status:</strong>
                                    <span class="badge badge-{{ $currentSubscription->status === 'active' ? 'success' : ($currentSubscription->status === 'cancelled' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($currentSubscription->status) }}
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Current Period:</strong><br>
                                    {{ $currentSubscription->current_period_start->format('M j, Y') }} - 
                                    {{ $currentSubscription->current_period_end->format('M j, Y') }}
                                </div>
                                
                                @if($currentSubscription->subscriptionPlan->token_cap_monthly > 0)
                                    <div class="mb-3">
                                        <strong>Monthly Token Allowance:</strong><br>
                                        {{ $currentSubscription->subscriptionPlan->formatted_token_cap }}
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Monthly Price:</strong>
                                    <span class="h5 text-primary">${{ number_format($currentSubscription->subscriptionPlan->monthly_price, 0) }}</span>
                                </div>
                                
                                @if($currentSubscription->status === 'active')
                                    <button wire:click="cancelSubscription" 
                                            class="btn btn-outline-danger"
                                            onclick="return confirm('Are you sure you want to cancel your subscription?')">
                                        <i class="fas fa-times"></i>
                                        Cancel Subscription
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <h5>No Active Subscription</h5>
                            <p class="text-muted">Choose a plan below to get started</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Token Usage Card -->
            @if($currentSubscription)
                <div class="card mt-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-chart-line"></i>
                            Token Usage
                        </h4>
                    </div>
                    <div class="card-body">
                        @if($currentSubscription->subscriptionPlan->token_cap_monthly > 0)
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span>Used this period:</span>
                                            <span><strong>{{ number_format($tokenUsageCurrentPeriod) }} tokens</strong></span>
                                        </div>
                                        <div class="progress mt-2">
                                            <div class="progress-bar {{ $this->getUsagePercentage() > 90 ? 'bg-danger' : ($this->getUsagePercentage() > 75 ? 'bg-warning' : 'bg-success') }}" 
                                                 style="width: {{ $this->getUsagePercentage() }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ number_format($this->getUsagePercentage(), 1) }}% of monthly allowance</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Remaining:</strong>
                                        <span class="text-{{ $this->getRemainingTokens() == 0 ? 'danger' : 'success' }}">
                                            {{ is_numeric($this->getRemainingTokens()) ? number_format($this->getRemainingTokens()) : $this->getRemainingTokens() }} tokens
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    @if($this->getOverageTokens() > 0)
                                        <div class="alert alert-warning">
                                            <strong>Overage Usage:</strong><br>
                                            {{ number_format($this->getOverageTokens()) }} tokens<br>
                                            <strong>Additional Cost:</strong> ${{ number_format($this->getOverageCost(), 2) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="fas fa-infinity"></i>
                                <strong>Unlimited Usage</strong><br>
                                Your plan includes unlimited token usage.
                            </div>
                        @endif

                        <!-- Usage History -->
                        @if(count($tokenUsageHistory) > 0)
                            <div class="mt-4">
                                <h6>Recent Usage History</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Tokens Used</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tokenUsageHistory->take(10) as $usage)
                                                <tr>
                                                    <td>{{ \Carbon\Carbon::parse($usage->date)->format('M j, Y') }}</td>
                                                    <td>{{ number_format($usage->total_tokens) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Available Plans Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-tags"></i>
                        Available Plans
                    </h4>
                </div>
                <div class="card-body">
                    @foreach($availablePlans as $plan)
                        <div class="card mb-3 {{ $currentSubscription && $currentSubscription->subscription_plan_id === $plan->id ? 'border-primary' : '' }}">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-1">{{ __('common.plan_' . $plan->slug . '_title') }}</h6>
                                        <p class="card-text small text-muted mb-2">{{ __('common.plan_' . $plan->slug . '_desc') }}</p>
                                        
                                        @if($plan->monthly_price > 0)
                                            <div class="text-primary">
                                                <strong>${{ number_format($plan->monthly_price, 0) }}/mo</strong>
                                            </div>
                                        @elseif($plan->slug === 'payg')
                                            <div class="text-primary">
                                                <strong>{{ __('common.plan_payg_title') }}</strong>
                                            </div>
                                        @else
                                            <div class="text-primary">
                                                <strong>{{ __('common.plan_enterprise_title') }}</strong>
                                            </div>
                                        @endif
                                        
                                        <small class="text-muted">
                                            @if($plan->token_cap_monthly > 0)
                                                {{ $plan->formatted_token_cap }} tokens/month
                                            @else
                                                {{ __('common.unlimited_tokens') }}
                                            @endif
                                        </small>

                                        <ul class="list-unstyled mt-2">
                                            @foreach($plan->features as $feature)
                                                <li>{{ __('common.plan_' . $plan->slug . '_feature_' . Str::slug($feature, '_')) }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    
                                    @if($currentSubscription && $currentSubscription->subscription_plan_id === $plan->id)
                                        <span class="badge badge-primary">{{ __('common.current_plan') }}</span>
                                    @else
                                        @if($plan->slug === 'enterprise')
                                            <a href="mailto:sales@ai-chat.support" class="btn btn-outline-primary btn-sm">
                                                {{ __('common.plan_enterprise_button') }}
                                            </a>
                                        @else
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="location.href='{{ route('home') }}#pricing'">
                                                {{ __('common.plan_' . $plan->slug . '_button') }}
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
