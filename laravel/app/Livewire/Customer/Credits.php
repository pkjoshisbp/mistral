<?php

namespace App\Livewire\Customer;

use App\Models\CreditTransaction;
use App\Models\UserCredit;
use Livewire\Component;

class Credits extends Component
{
    public array $creditSummary = [];
    public $recentTransactions;

    public function mount()
    {
        $user = auth()->user();
        $userCredit = UserCredit::getOrCreateForUser($user->id);

        $this->creditSummary = $userCredit->getUsableCreditSummary();

        $this->recentTransactions = CreditTransaction::where('user_id', $user->id)
            ->whereIn('type', ['credit', 'debit'])
            ->latest()
            ->limit(8)
            ->get();
    }

    public function render()
    {
        return view('livewire.customer.credits')->layout('layouts.customer');
    }
}
