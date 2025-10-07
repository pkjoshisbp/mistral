<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShopifyWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;
    public $organization;
    public $loginUrl;
    public $dashboardUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $password, Organization $organization)
    {
        $this->user = $user;
        $this->password = $password;
        $this->organization = $organization;
        $this->loginUrl = config('app.url') . '/login';
        $this->dashboardUrl = config('app.url') . '/customer/dashboard';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to AI Chat Support - Your Shopify App is Installed!')
                    ->markdown('emails.shopify-welcome');
    }
}
