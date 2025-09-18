<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;

class CreditManager extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedUserId = null;
    public $showCreditModal = false;
    public $showSubscriptionModal = false;
    
    // Credit adjustment form
    public $creditAmount = '';
    public $creditReason = '';
    public $creditType = 'add'; // add or deduct
    
    // Offline subscription form
    public $subscriptionPlanId = '';
    public $billingCycle = 'monthly';
    public $subscriptionStartDate = '';
    public $subscriptionEndDate = '';
    public $offlinePaymentAmount = '';
    public $offlinePaymentReference = '';
    public $offlinePaymentMethod = 'bank_transfer';
    public $subscriptionNotes = '';

    protected $rules = [
        'creditAmount' => 'required|numeric|min:0.01|max:10000',
        'creditReason' => 'required|string|max:255',
        'creditType' => 'required|in:add,deduct',
        'subscriptionPlanId' => 'required|exists:subscription_plans,id',
        'billingCycle' => 'required|in:monthly,yearly',
        'subscriptionStartDate' => 'required|date|after_or_equal:today',
        'subscriptionEndDate' => 'required|date|after:subscriptionStartDate',
        'offlinePaymentAmount' => 'required|numeric|min:0',
        'offlinePaymentReference' => 'required|string|max:255',
        'offlinePaymentMethod' => 'required|in:bank_transfer,cash,check,other',
        'subscriptionNotes' => 'nullable|string|max:500'
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreditModal($userId)
    {
        $this->selectedUserId = $userId;
        $this->resetCreditForm();
        $this->showCreditModal = true;
    }

    public function openSubscriptionModal($userId)
    {
        $this->selectedUserId = $userId;
        $this->resetSubscriptionForm();
        $this->subscriptionStartDate = Carbon::today()->format('Y-m-d');
        $this->subscriptionEndDate = Carbon::today()->addMonth()->format('Y-m-d');
        $this->showSubscriptionModal = true;
    }

    public function updatedSubscriptionPlanId()
    {
        $this->updateSubscriptionEndDate();
    }

    public function updatedBillingCycle()
    {
        $this->updateSubscriptionEndDate();
    }

    public function updatedSubscriptionStartDate()
    {
        $this->updateSubscriptionEndDate();
    }

    private function updateSubscriptionEndDate()
    {
        if ($this->subscriptionStartDate && $this->billingCycle) {
            $startDate = Carbon::parse($this->subscriptionStartDate);
            $this->subscriptionEndDate = $this->billingCycle === 'yearly' 
                ? $startDate->copy()->addYear()->format('Y-m-d')
                : $startDate->copy()->addMonth()->format('Y-m-d');
        }
    }

    public function adjustCredits()
    {
        $this->validate([
            'creditAmount' => 'required|numeric|min:0.01|max:10000',
            'creditReason' => 'required|string|max:255',
            'creditType' => 'required|in:add,deduct'
        ]);

        $user = User::findOrFail($this->selectedUserId);
        $userCredit = UserCredit::getOrCreateForUser($user->id);

        if ($this->creditType === 'add') {
            $userCredit->addCredits(
                $this->creditAmount, 
                'Admin adjustment: ' . $this->creditReason
            );
            $message = "Successfully added {$this->creditAmount} credits to {$user->name}'s account.";
        } else {
            if ($userCredit->hasSufficientCredits($this->creditAmount)) {
                $userCredit->deductCredits(
                    $this->creditAmount, 
                    'Admin deduction: ' . $this->creditReason
                );
                $message = "Successfully deducted {$this->creditAmount} credits from {$user->name}'s account.";
            } else {
                session()->flash('error', "Insufficient credits. User only has {$userCredit->balance} credits available.");
                return;
            }
        }

        session()->flash('success', $message);
        $this->showCreditModal = false;
        $this->resetCreditForm();
    }

    public function createOfflineSubscription()
    {
        $this->validate([
            'subscriptionPlanId' => 'required|exists:subscription_plans,id',
            'billingCycle' => 'required|in:monthly,yearly',
            'subscriptionStartDate' => 'required|date|after_or_equal:today',
            'subscriptionEndDate' => 'required|date|after:subscriptionStartDate',
            'offlinePaymentAmount' => 'required|numeric|min:0',
            'offlinePaymentReference' => 'required|string|max:255',
            'offlinePaymentMethod' => 'required|in:bank_transfer,cash,check,other',
            'subscriptionNotes' => 'nullable|string|max:500'
        ]);

        $user = User::findOrFail($this->selectedUserId);
        $plan = SubscriptionPlan::findOrFail($this->subscriptionPlanId);

        // Cancel existing active subscriptions
        $user->subscriptions()->where('status', 'active')->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        // Create new subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'organization_id' => $user->organizations->first()?->id, // Use first organization if available
            'subscription_plan_id' => $this->subscriptionPlanId,
            'paypal_subscription_id' => null,
            'razorpay_subscription_id' => null,
            'razorpay_payment_id' => null,
            'billing_cycle' => $this->billingCycle,
            'status' => 'active',
            'current_period_start' => Carbon::parse($this->subscriptionStartDate),
            'current_period_end' => Carbon::parse($this->subscriptionEndDate),
            'tokens_used_this_period' => 0,
            'overage_charges' => 0,
            'cancelled_at' => null
        ]);

        // Create payment record
        CreditTransaction::create([
            'user_id' => $user->id,
            'type' => 'offline_payment',
            'amount' => $this->offlinePaymentAmount,
            'description' => "Offline subscription payment ({$this->offlinePaymentMethod}): {$this->offlinePaymentReference}",
            'reference_id' => $this->offlinePaymentReference,
            'subscription_id' => $subscription->id,
            'notes' => $this->subscriptionNotes
        ]);

        session()->flash('success', "Successfully created offline subscription for {$user->name}. Plan: {$plan->name} ({$this->billingCycle})");
        $this->showSubscriptionModal = false;
        $this->resetSubscriptionForm();
    }

    public function cancelSubscription($subscriptionId)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        session()->flash('success', "Subscription cancelled successfully.");
    }

    public function extendSubscription($subscriptionId, $months = 1)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        
        if ($subscription->status === 'active') {
            $newEndDate = $subscription->current_period_end->addMonths($months);
            $subscription->update(['current_period_end' => $newEndDate]);
            
            CreditTransaction::create([
                'user_id' => $subscription->user_id,
                'type' => 'subscription_extension',
                'amount' => 0,
                'description' => "Admin extension: {$months} month(s) added to subscription",
                'subscription_id' => $subscriptionId
            ]);

            session()->flash('success', "Subscription extended by {$months} month(s). New end date: " . $newEndDate->format('Y-m-d'));
        } else {
            session()->flash('error', 'Cannot extend inactive subscription.');
        }
    }

    public function resetCreditForm()
    {
        $this->creditAmount = '';
        $this->creditReason = '';
        $this->creditType = 'add';
    }

    public function resetSubscriptionForm()
    {
        $this->subscriptionPlanId = '';
        $this->billingCycle = 'monthly';
        $this->subscriptionStartDate = '';
        $this->subscriptionEndDate = '';
        $this->offlinePaymentAmount = '';
        $this->offlinePaymentReference = '';
        $this->offlinePaymentMethod = 'bank_transfer';
        $this->subscriptionNotes = '';
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->with(['userCredit', 'subscriptions' => function($query) {
                $query->with('subscriptionPlan')->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $subscriptionPlans = SubscriptionPlan::where('is_active', true)->get();

        return view('livewire.admin.credit-manager', compact('users', 'subscriptionPlans'))
            ->layout('layouts.admin');
    }
}