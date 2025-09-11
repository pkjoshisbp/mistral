<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailOtp;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $selectedPlan = $request->get('plan');
        return view('auth.register', compact('selectedPlan'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'otp' => ['required_if:has_otp,true', 'string', 'size:6'],
        ]);

        // Validate OTP if provided
        if ($request->filled('otp')) {
            $validationResult = $this->validateOtp($request);
            if (!$validationResult['valid']) {
                throw ValidationException::withMessages([
                    'otp' => [$validationResult['message']],
                ]);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer', // Set role as customer
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Check if a plan was selected during registration
        $selectedPlan = $request->get('plan');
        if ($selectedPlan && $selectedPlan !== 'enterprise') {
            // Store the selected plan in session for payment redirect
            session(['selected_plan' => $selectedPlan]);
            return redirect()->route('customer.subscription')->with([
                'plan' => $selectedPlan,
                'message' => 'Registration successful! Please choose your subscription plan.'
            ]);
        }

        // Default redirect to customer dashboard for users without plan selection
        return redirect()->route('customer.dashboard')->with('message', 'Registration successful! Welcome to AI Chat Support.');
    }

    /**
     * Validate OTP for registration
     */
    private function validateOtp(Request $request): array
    {
        $otp = $request->input('otp');
        $email = $request->input('email');

        if (!$otp || !$email) {
            return [
                'valid' => false,
                'message' => 'OTP and email are required.'
            ];
        }

        $emailOtp = EmailOtp::where('email', $email)
            ->where('otp', $otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$emailOtp) {
            return [
                'valid' => false,
                'message' => 'Invalid or expired OTP.'
            ];
        }

        if ($emailOtp->verified_at) {
            return [
                'valid' => false,
                'message' => 'OTP has already been used.'
            ];
        }

        // Mark OTP as verified
        $emailOtp->markAsVerified();

        return [
            'valid' => true,
            'message' => 'OTP verified successfully.'
        ];
    }
}
