<?php

namespace App\Livewire;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Illuminate\Validation\Rules\Password;

class AffiliateRegistration extends Component
{
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $phone = '';
    public $company = '';
    public $description = '';
    public $commission_type = 'one-time';
    public $website = '';
    public $experience = '';
    public $marketing_channels = '';
    public $monthly_traffic = '';
    public $terms_accepted = false;

    public $showSuccess = false;
    public $errorMessage = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => ['required', 'confirmed'],
        'phone' => 'required|string|max:20',
        'company' => 'nullable|string|max:255',
        'description' => 'required|string|min:50|max:1000',
        'commission_type' => 'required|in:one-time,recurring',
        'website' => 'nullable|url|max:255',
        'experience' => 'required|string|max:500',
        'marketing_channels' => 'required|string|max:500',
        'monthly_traffic' => 'required|string|max:100',
        'terms_accepted' => 'required|accepted'
    ];

    protected $messages = [
        'description.min' => 'Please provide at least 50 characters describing why you want to become an affiliate.',
        'terms_accepted.required' => 'You must accept the terms and conditions to proceed.',
        'experience.required' => 'Please describe your marketing experience.',
        'marketing_channels.required' => 'Please describe your marketing channels.',
        'monthly_traffic.required' => 'Please provide your estimated monthly traffic/reach.'
    ];

    public function submit()
    {
        $this->validate();

        try {
            // Create user account
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => 'affiliate',
                'email_verified_at' => now() // Auto-verify affiliate emails
            ]);

            // Create affiliate profile
            $affiliate = Affiliate::create([
                'user_id' => $user->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'company' => $this->company,
                'description' => $this->description,
                'commission_type' => $this->commission_type,
                'status' => 'pending',
                'metadata' => [
                    'website' => $this->website,
                    'experience' => $this->experience,
                    'marketing_channels' => $this->marketing_channels,
                    'monthly_traffic' => $this->monthly_traffic,
                    'application_date' => now()->toDateTimeString()
                ]
            ]);

            // Send notification emails
            $this->sendAffiliateWelcomeEmail($affiliate);
            $this->sendAdminNotificationEmail($affiliate);

            $this->showSuccess = true;
            $this->reset([
                'name', 'email', 'password', 'password_confirmation', 
                'phone', 'company', 'description', 'website', 
                'experience', 'marketing_channels', 'monthly_traffic'
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Registration failed. Please try again. Error: ' . $e->getMessage();
        }
    }

    private function sendAffiliateWelcomeEmail($affiliate)
    {
        // TODO: Create and send welcome email to affiliate
        // This would include next steps, what to expect, etc.
    }

    private function sendAdminNotificationEmail($affiliate)
    {
        // TODO: Send notification to admin about new affiliate application
    }

    public function getCommissionRates()
    {
        return [
            'one-time' => [
                'rate' => '20-40%',
                'description' => 'One-time commission on first purchase only',
                'benefit' => 'Higher commission rate'
            ],
            'recurring' => [
                'rate' => '5-15%', 
                'description' => 'Commission on all purchases for 3 years',
                'benefit' => 'Long-term recurring income'
            ]
        ];
    }

    public function render()
    {
        return view('livewire.affiliate-registration', [
            'commission_rates' => $this->getCommissionRates()
        ])->layout('layouts.public');
    }
}
