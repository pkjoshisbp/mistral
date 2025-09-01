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
            'is_active' => true,
        ]);

        // Add the user to the organization using our many-to-many relationship
        Auth::user()->organizations()->attach($organization->id);

        session()->flash('success', 'Organization created successfully!');
        
        return redirect()->route('customer.dashboard');
    }

    public function requestAccess($organizationId)
    {
        $organization = Organization::findOrFail($organizationId);
        
        // Check if user is already assigned to this organization
        if (Auth::user()->organizations->contains($organizationId)) {
            session()->flash('error', 'You are already a member of this organization.');
            return;
        }
        
        // Add user to organization
        Auth::user()->organizations()->attach($organizationId);
        
        session()->flash('success', 'You have been added to the organization!');
        
        return redirect()->route('customer.dashboard');
    }

    public function render()
    {
        return view('livewire.customer.organization-setup')
            ->layout('layouts.customer')
            ->layoutData(['title' => 'Organization Setup']);
    }
}
