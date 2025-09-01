<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        ]);

        // Validate hCaptcha if configured
        if (config('services.hcaptcha.site_key') && config('services.hcaptcha.secret_key')) {
            $request->validate([
                'h-captcha-response' => 'required|string',
            ]);

            // Verify hCaptcha with server-side validation
            $hcaptchaResponse = $request->input('h-captcha-response');
            $verifyResponse = Http::asForm()->post('https://hcaptcha.com/siteverify', [
                'secret' => config('services.hcaptcha.secret_key'),
                'response' => $hcaptchaResponse,
                'remoteip' => $request->ip(),
            ]);

            $hcaptchaResult = $verifyResponse->json();
            
            if (!$hcaptchaResult['success']) {
                throw ValidationException::withMessages([
                    'h-captcha-response' => ['The hCaptcha verification failed. Please try again.'],
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
            // Store the selected plan in session for after login redirect
            session(['selected_plan' => $selectedPlan]);
            return redirect()->route('customer.subscription')->with('plan', $selectedPlan);
        }

        return redirect(RouteServiceProvider::HOME);
    }
}
