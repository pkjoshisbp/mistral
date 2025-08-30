<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationSetup extends Component
{
    public $tab = 'create'; // 'create' or 'join'
    public $name = '';
    public $slug = '';
    public $description = '';
    public $existingOrganizations = [];

    public function mount()
    {
        // Get existing organizations that the user could potentially join
        $this->existingOrganizations = Organization::all();
    }

    public function updatedName()
    {
        $this->slug = Str::slug($this->name);
    }

    public function createOrganization()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug',
            'description' => 'nullable|string|max:1000',
        ]);

        $organization = Organization::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'owner_id' => Auth::id(),
        ]);

        // Add the user to the organization
        $organization->users()->attach(Auth::id(), ['role' => 'owner']);

        session()->flash('success', 'Organization created successfully!');
        
        return redirect()->route('customer.dashboard');
    }

    public function requestAccess($organizationId)
    {
        $organization = Organization::findOrFail($organizationId);
        
        // For now, we'll auto-approve the request
        // In a real application, this would send a request to the organization owner
        $organization->users()->attach(Auth::id(), ['role' => 'member']);
        
        session()->flash('success', 'You have been added to the organization!');
        
        return redirect()->route('customer.dashboard');
    }

    public function render()
    {
        return view('livewire.customer.organization-setup');
    }
}
