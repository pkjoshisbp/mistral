<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Redirect based on user role
        $user = auth()->user();
            // If a plan selection was stored pre-login (from pricing), redirect to home with flags to resume checkout
            $selectedPlanId = $request->session()->pull('selected_plan_id');
            $paymentProvider = $request->session()->pull('payment_provider');
            $billingCycle = $request->session()->pull('billing_cycle');
            if ($selectedPlanId && $paymentProvider) {
                // Append as query params for the welcome page JS to pick up (and as a backup to sessionStorage)
                return redirect()->to(route('home') . "?resume_payment=1&plan_id={$selectedPlanId}&provider={$paymentProvider}" . ($billingCycle ? "&cycle={$billingCycle}" : ''));
            }
        if ($user->role === 'admin') {
            return redirect()->intended(route('admin.dashboard'));
        } elseif ($user->role === 'customer') {
            return redirect()->intended(route('customer.dashboard'));
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
