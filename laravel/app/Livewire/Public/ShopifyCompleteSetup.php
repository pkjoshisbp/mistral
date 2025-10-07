<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class ShopifyCompleteSetup extends Component
{
    public $org_id;
    public $shop;
    public $organization;
    
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    
    public $errorMessage = '';
    public $successMessage = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function mount($org_id, $shop = null)
    {
        $this->org_id = $org_id;
        $this->shop = $shop;
        
        $this->organization = Organization::find($org_id);
        
        if (!$this->organization) {
            abort(404, 'Organization not found');
        }

        // Pre-fill email from organization if available
        if ($this->organization->contact_email) {
            $this->email = $this->organization->contact_email;
        }
    }

    public function completeSetup()
    {
        $this->validate();

        try {
            // Create user
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'email_verified_at' => now(), // Auto-verify from Shopify
            ]);

            Log::info('User created via Shopify complete setup', [
                'user_id' => $user->id,
                'org_id' => $this->org_id
            ]);

            // Associate with organization
            $this->organization->users()->attach($user->id);

            Log::info('User associated with organization via complete setup', [
                'user_id' => $user->id,
                'org_id' => $this->org_id
            ]);

            // Auto-login
            Auth::login($user);

            // Redirect to dashboard
            return redirect()->route('customer.dashboard')
                ->with('success', 'Account created successfully! Your Shopify store is connected.');

        } catch (\Exception $e) {
            Log::error('Failed to complete Shopify setup', [
                'org_id' => $this->org_id,
                'error' => $e->getMessage()
            ]);
            
            $this->errorMessage = 'Failed to create account. Please try again or contact support.';
        }
    }

    public function render()
    {
        return view('livewire.public.shopify-complete-setup')
            ->layout('layouts.public')
            ->title('Complete Your AI Chat Support Setup');
    }
}
