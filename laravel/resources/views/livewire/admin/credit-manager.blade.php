<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Credit & Subscription Management</h4>
                    <div class="d-flex gap-2">
                        <input type="text" wire:model.live="search" class="form-control" placeholder="Search users..." style="width: 250px;">
                    </div>
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

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Credit Balance</th>
                                    <th>Active Subscription</th>
                                    <th>Usage Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $user->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $user->email }}</small>
                                            <br>
                                            <span class="badge bg-{{ $user->role === 'admin' ? 'danger' : 'primary' }} badge-sm">{{ ucfirst($user->role) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-success">{{ number_format($user->userCredit?->balance ?? 0, 2) }}</strong> credits
                                            @if($user->userCredit)
                                            <br>
                                            <small class="text-muted">
                                                Purchased: {{ number_format($user->userCredit->total_purchased, 2) }} | 
                                                Used: {{ number_format($user->userCredit->total_used, 2) }}
                                            </small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php $activeSubscription = $user->subscriptions->where('status', 'active')->first(); @endphp
                                        @if($activeSubscription)
                                            <div>
                                                <strong class="text-primary">{{ $activeSubscription->subscriptionPlan->name }}</strong>
                                                <br>
                                                <small class="text-muted">
                                                    {{ ucfirst($activeSubscription->billing_cycle) }} | 
                                                    Expires: {{ $activeSubscription->current_period_end->format('M j, Y') }}
                                                </small>
                                            </div>
                                        @else
                                            <span class="text-muted">No active subscription</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($activeSubscription)
                                            @php 
                                                $plan = $activeSubscription->subscriptionPlan;
                                                $usedTokens = $activeSubscription->tokens_used_this_period;
                                                $totalTokens = $plan->token_cap_monthly;
                                                $percentage = $totalTokens > 0 ? ($usedTokens / $totalTokens) * 100 : 0;
                                            @endphp
                                            <div>
                                                <small>{{ number_format($usedTokens) }} / {{ number_format($totalTokens) }} tokens</small>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-{{ $percentage > 90 ? 'danger' : ($percentage > 70 ? 'warning' : 'success') }}" 
                                                         style="width: {{ min(100, $percentage) }}%"></div>
                                                </div>
                                                <small class="text-muted">{{ number_format($percentage, 1) }}% used</small>
                                            </div>
                                        @elseif($user->userCredit && $user->userCredit->balance > 0)
                                            <span class="badge bg-info">Using Credits</span>
                                        @else
                                            <span class="badge bg-warning">No Access</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    wire:click="openCreditModal({{ $user->id }})"
                                                    title="Adjust Credits">
                                                <i class="fas fa-coins"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    wire:click="openSubscriptionModal({{ $user->id }})"
                                                    title="Manage Subscription">
                                                <i class="fas fa-calendar-plus"></i>
                                            </button>
                                            @if($activeSubscription)
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" 
                                                        data-bs-toggle="dropdown" title="Quick Actions">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" wire:click.prevent="extendSubscription({{ $activeSubscription->id }}, 1)">
                                                        <i class="fas fa-plus-circle me-1"></i> Extend 1 Month
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" wire:click.prevent="extendSubscription({{ $activeSubscription->id }}, 3)">
                                                        <i class="fas fa-plus-circle me-1"></i> Extend 3 Months
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" wire:click.prevent="cancelSubscription({{ $activeSubscription->id }})"
                                                           onclick="return confirm('Are you sure you want to cancel this subscription?')">
                                                        <i class="fas fa-times-circle me-1"></i> Cancel Subscription
                                                    </a></li>
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Credit Adjustment Modal -->
    @if($showCreditModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="adjustCredits">
                    <div class="modal-header">
                        <h5 class="modal-title">Adjust User Credits</h5>
                        <button type="button" class="btn-close" wire:click="$set('showCreditModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        @php $selectedUser = $users->firstWhere('id', $selectedUserId) ?? \App\Models\User::find($selectedUserId); @endphp
                        @if($selectedUser)
                        <div class="alert alert-info">
                            <strong>{{ $selectedUser->name }}</strong><br>
                            Current Balance: <strong>{{ number_format($selectedUser->userCredit?->balance ?? 0, 2) }} credits</strong>
                        </div>
                        @endif
                        
                        <div class="mb-3">
                            <label class="form-label">Action Type</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" wire:model="creditType" value="add" id="addCredits">
                                    <label class="form-check-label text-success" for="addCredits">
                                        <i class="fas fa-plus-circle"></i> Add Credits
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" wire:model="creditType" value="deduct" id="deductCredits">
                                    <label class="form-check-label text-danger" for="deductCredits">
                                        <i class="fas fa-minus-circle"></i> Deduct Credits
                                    </label>
                                </div>
                            </div>
                            @error('creditType') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Credits (Tokens)</label>
                            <input type="number" class="form-control @error('creditAmount') is-invalid @enderror" 
                                   wire:model="creditAmount" step="1" min="1" max="100000000" placeholder="e.g., 100000 (tokens)">
                            @error('creditAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted">Enter how many tokens to add or deduct. Large values like 100,000 or 200,000 are supported.</small>
                        </div>

                        @if($creditType === 'add')
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="toggleOfflinePayment"
                                       onclick="document.getElementById('offlinePaymentFields').classList.toggle('d-none');">
                                <label class="form-check-label" for="toggleOfflinePayment">Add offline payment details (optional)</label>
                            </div>
                        </div>
                        <div id="offlinePaymentFields" class="border rounded p-3 d-none">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Payment Amount</label>
                                    <input type="number" step="0.01" min="0" class="form-control @error('offlineCreditPaymentAmount') is-invalid @enderror" 
                                           wire:model="offlineCreditPaymentAmount" placeholder="0.00">
                                    @error('offlineCreditPaymentAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select @error('offlineCreditPaymentCurrency') is-invalid @enderror" wire:model="offlineCreditPaymentCurrency">
                                        <option value="INR">INR</option>
                                        <option value="USD">USD</option>
                                    </select>
                                    @error('offlineCreditPaymentCurrency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Method</label>
                                    <select class="form-select @error('offlineCreditPaymentMethod') is-invalid @enderror" wire:model="offlineCreditPaymentMethod">
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="check">Check</option>
                                        <option value="other">Other</option>
                                    </select>
                                    @error('offlineCreditPaymentMethod') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Reference / Receipt</label>
                                    <input type="text" class="form-control @error('offlineCreditPaymentReference') is-invalid @enderror" 
                                           wire:model="offlineCreditPaymentReference" placeholder="e.g., UTR/Receipt/Invoice #">
                                    @error('offlineCreditPaymentReference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <small class="text-muted">These details are saved with the credit transaction as payment_method=offline.</small>
                        </div>
                        @endif
                        
                        <div class="mb-3 mt-3">
                            <label class="form-label">Reason / Notes</label>
                            <input type="text" class="form-control @error('creditReason') is-invalid @enderror" 
                                   wire:model="creditReason" placeholder="e.g., Free trial extension, Promotional credit, Refund">
                            @error('creditReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showCreditModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-{{ $creditType === 'add' ? 'success' : 'danger' }}">
                            {{ $creditType === 'add' ? 'Add' : 'Deduct' }} Credits
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Offline Subscription Modal -->
    @if($showSubscriptionModal)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="createOfflineSubscription">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Offline Subscription</h5>
                        <button type="button" class="btn-close" wire:click="$set('showSubscriptionModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        @php $selectedUser = $users->firstWhere('id', $selectedUserId) ?? \App\Models\User::find($selectedUserId); @endphp
                        @if($selectedUser)
                        <div class="alert alert-info">
                            <strong>{{ $selectedUser->name }}</strong> ({{ $selectedUser->email }})<br>
                            <small>This will create a subscription for offline payment (bank transfer, cash, check, etc.)</small>
                        </div>
                        @endif
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subscription Plan</label>
                                <select class="form-select @error('subscriptionPlanId') is-invalid @enderror" wire:model="subscriptionPlanId">
                                    <option value="">Select Plan</option>
                                    @foreach($subscriptionPlans as $plan)
                                    <option value="{{ $plan->id }}">
                                        {{ $plan->name }} - ${{ $plan->monthly_price }}/mo, ${{ $plan->yearly_price }}/yr
                                    </option>
                                    @endforeach
                                </select>
                                @error('subscriptionPlanId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Billing Cycle</label>
                                <select class="form-select @error('billingCycle') is-invalid @enderror" wire:model="billingCycle">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                                @error('billingCycle') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control @error('subscriptionStartDate') is-invalid @enderror" 
                                       wire:model="subscriptionStartDate">
                                @error('subscriptionStartDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control @error('subscriptionEndDate') is-invalid @enderror" 
                                       wire:model="subscriptionEndDate" readonly>
                                @error('subscriptionEndDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        
                        <hr>
                        <h6>Payment Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Amount ($)</label>
                                <input type="number" class="form-control @error('offlinePaymentAmount') is-invalid @enderror" 
                                       wire:model="offlinePaymentAmount" step="0.01" min="0" placeholder="0.00">
                                @error('offlinePaymentAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-select @error('offlinePaymentMethod') is-invalid @enderror" wire:model="offlinePaymentMethod">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="other">Other</option>
                                </select>
                                @error('offlinePaymentMethod') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Reference</label>
                            <input type="text" class="form-control @error('offlinePaymentReference') is-invalid @enderror" 
                                   wire:model="offlinePaymentReference" placeholder="Transaction ID, Check number, etc.">
                            @error('offlinePaymentReference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control @error('subscriptionNotes') is-invalid @enderror" 
                                      wire:model="subscriptionNotes" rows="2" 
                                      placeholder="Additional notes about this offline subscription..."></textarea>
                            @error('subscriptionNotes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showSubscriptionModal', false)">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Subscription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    

<style>
.modal.show.d-block {
    display: block !important;
}
</style>
</div>