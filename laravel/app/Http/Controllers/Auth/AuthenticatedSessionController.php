<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

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
        // Check if this is OTP authentication
        if ($request->has('otp')) {
            return $this->authenticateWithOtp($request);
        }

        // Regular password authentication
        $request->authenticate();

        $request->session()->regenerate();

        return $this->redirectAfterLogin($request);
    }

    /**
     * Handle OTP authentication
     */
    protected function authenticateWithOtp(LoginRequest $request): RedirectResponse
    {
        $user = \App\Models\User::where('email', $request->email)->first();
        
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // OTP validation is already done in LoginRequest
        Auth::login($user, $request->boolean('remember'));

        // Store device as trusted if remember_device is checked
        if ($request->boolean('remember_device')) {
            $deviceFingerprint = $request->header('X-Device-Fingerprint');
            if ($deviceFingerprint) {
                $trustedDevices = json_decode($request->cookie('trusted_devices', '[]'), true);
                if (!in_array($deviceFingerprint, $trustedDevices)) {
                    $trustedDevices[] = $deviceFingerprint;
                    // Keep only last 3 trusted devices
                    $trustedDevices = array_slice($trustedDevices, -3);
                    cookie()->queue('trusted_devices', json_encode($trustedDevices), 60 * 24 * 30); // 30 days
                }
            }
        }

        $request->session()->regenerate();

        return $this->redirectAfterLogin($request);
    }

    /**
     * Redirect user after successful login based on role and session data
     */
    protected function redirectAfterLogin(LoginRequest $request): RedirectResponse
    {
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
