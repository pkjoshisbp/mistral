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
    public $website = '';
    public $contact_email = '';
    public $contact_phone = '';
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
            'website' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:32',
        ]);

        $organization = Organization::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website_url' => $this->website,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
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
