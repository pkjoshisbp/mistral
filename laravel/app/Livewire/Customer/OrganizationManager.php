<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationManager extends Component
{
    public $name = '';
    public $slug = '';
    public $description = '';
    public $website = '';
    public $timezone = 'UTC';
    public $organization; // existing org

    protected $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:organizations,slug',
        'description' => 'nullable|string|max:1000',
        'website' => 'nullable|url|max:255',
        'timezone' => 'required|string|max:100'
    ];

    public function mount()
    {
    $this->organization = Auth::user()->primaryOrganization();
        if ($this->organization) {
            $this->fill([
                'name' => $this->organization->name,
                'slug' => $this->organization->slug,
                'description' => $this->organization->description,
                'website' => $this->organization->website,
                'timezone' => $this->organization->timezone ?? 'UTC'
            ]);
        }
    }

    public function updatedName()
    {
        if (!$this->organization) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save()
    {
        if ($this->organization) {
            // Update existing
            $this->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'website' => 'nullable|url|max:255',
                'timezone' => 'required|string|max:100'
            ]);
            $this->organization->update([
                'name' => $this->name,
                'description' => $this->description,
                'website' => $this->website,
                'timezone' => $this->timezone
            ]);
            session()->flash('success', 'Organization updated successfully.');
        } else {
            $this->validate();
            $org = Organization::create([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'website' => $this->website,
                'timezone' => $this->timezone,
                'is_active' => true
            ]);
            Auth::user()->organizations()->attach($org->id);
            $this->organization = $org;
            session()->flash('success', 'Organization created and linked to your account.');
        }
    }

    public function render()
    {
        return view('livewire.customer.organization-manager')
            ->layout('layouts.customer')
            ->layoutData(['title' => 'Organization']);
    }
}
