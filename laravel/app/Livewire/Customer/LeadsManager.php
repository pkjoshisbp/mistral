<?php
namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Lead;
use Illuminate\Support\Facades\Auth;

class LeadsManager extends Component
{
    public function getLeadsProperty()
    {
        return Lead::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.customer.leads-manager')->layout('layouts.customer');
    }
}
