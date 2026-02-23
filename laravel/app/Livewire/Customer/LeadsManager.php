<?php
namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Lead;
use Illuminate\Support\Facades\Auth;

class LeadsManager extends Component
{
    public string $search = '';
    public string $statusFilter = '';

    public function getLeadsProperty()
    {
        $user = Auth::user();

        // Get all organization IDs the customer belongs to
        $orgIds = $user->organizations()->pluck('organizations.id');

        // Fallback to legacy single org column if pivot returns nothing
        if ($orgIds->isEmpty() && $user->organization_id) {
            $orgIds = collect([$user->organization_id]);
        }

        $query = Lead::whereIn('organization_id', $orgIds)
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('phone', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->get();
    }

    public function render()
    {
        return view('livewire.customer.leads-manager')->layout('layouts.customer');
    }
}
