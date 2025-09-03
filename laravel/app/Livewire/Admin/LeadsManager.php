<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Lead;
use App\Models\Organization;

class LeadsManager extends Component
{
    public $organizationId = '';

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getLeadsProperty()
    {
        $query = Lead::query();
        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }
        return $query->orderBy('created_at', 'desc')->get();
    }

    public function render()
    {
        return view('livewire.admin.leads-manager')->layout('layouts.admin');
    }
}
