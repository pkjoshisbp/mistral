<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Affiliate;

class AffiliateProfile extends Component
{
    public $name;
    public $email;
    public $phone;
    public $website;
    public $description;
    public $marketing_experience;
    public $commission_type;

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'nullable|string|max:20',
        'website' => 'nullable|url|max:255',
        'description' => 'nullable|string|max:1000',
        'marketing_experience' => 'nullable|string|max:1000',
        'commission_type' => 'required|in:one-time,recurring'
    ];

    public function mount()
    {
        $user = auth()->user();
        $affiliate = $user->affiliate;

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $affiliate->phone ?? '';
        $this->website = $affiliate->website ?? '';
        $this->description = $affiliate->description ?? '';
        $this->marketing_experience = $affiliate->marketing_experience ?? '';
        $this->commission_type = $affiliate->commission_type;
    }

    public function updateProfile()
    {
        $this->validate();

        $user = auth()->user();
        $affiliate = $user->affiliate;

        // Update user info
        $user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        // Update affiliate info
        $affiliate->update([
            'phone' => $this->phone,
            'website' => $this->website,
            'description' => $this->description,
            'marketing_experience' => $this->marketing_experience,
            'commission_type' => $this->commission_type,
        ]);

        session()->flash('message', 'Profile updated successfully!');
    }

    public function render()
    {
        $affiliate = auth()->user()->affiliate;
        
        return view('livewire.affiliate-profile', compact('affiliate'))
            ->layout('layouts.affiliate');
    }
}