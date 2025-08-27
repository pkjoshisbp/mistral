<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class RedirectAfterLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        
        // Set the intended URL based on user role
        if ($user->role === 'admin') {
            session(['url.intended' => route('admin.dashboard')]);
        } elseif ($user->role === 'customer') {
            session(['url.intended' => route('customer.dashboard')]);
        }
    }
}
