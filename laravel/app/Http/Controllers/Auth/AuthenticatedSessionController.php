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
    public function create(Request $request): View
    {
        // Store intended redirect URL if provided
        if ($request->has('redirect')) {
            $request->session()->put('url.intended', $request->get('redirect'));
        }
        
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        \Log::info('Login attempt', [
            'email' => $request->email,
            'has_otp' => $request->has('otp'),
            'has_password' => $request->has('password'),
            'request_data' => $request->except(['password', 'otp'])
        ]);

        // Check if this is OTP authentication
        if ($request->has('otp')) {
            \Log::info('Processing OTP authentication', ['email' => $request->email]);
            return $this->authenticateWithOtp($request);
        }

        // Regular password authentication
        \Log::info('Processing regular authentication', ['email' => $request->email]);
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
        
        // Check if there's an intended URL (like credits-and-services page)
        $intendedUrl = $request->session()->get('url.intended');
        if ($intendedUrl && !str_contains($intendedUrl, '/customer/') && !str_contains($intendedUrl, '/admin/')) {
            // If intended URL is a public page (like credits), redirect there
            $request->session()->forget('url.intended');
            return redirect($intendedUrl);
        }
        
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
        } elseif ($user->role === 'affiliate') {
            return redirect()->intended(route('affiliate.dashboard'));
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
