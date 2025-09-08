<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];

        // Add hCaptcha validation if configured
        if (config('services.hcaptcha.site_key') && config('services.hcaptcha.secret_key')) {
            $rules['h-captcha-response'] = ['required', 'string'];
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate hCaptcha if configured
            if (config('services.hcaptcha.site_key') && config('services.hcaptcha.secret_key')) {
                $this->validateHCaptcha($validator);
            }
        });
    }

    /**
     * Validate hCaptcha response
     */
    private function validateHCaptcha($validator)
    {
        $hcaptchaResponse = $this->input('h-captcha-response');
        
        if (!$hcaptchaResponse) {
            $validator->errors()->add('h-captcha-response', 'Please complete the captcha verification.');
            return;
        }

        try {
            $verifyResponse = Http::timeout(10)->asForm()->post('https://hcaptcha.com/siteverify', [
                'secret' => config('services.hcaptcha.secret_key'),
                'response' => $hcaptchaResponse,
                'remoteip' => $this->ip(),
            ]);

            $hcaptchaResult = $verifyResponse->json();

            if (!$hcaptchaResult['success']) {
                $validator->errors()->add('h-captcha-response', 'The captcha verification failed. Please try again.');
            }
        } catch (\Exception $e) {
            // If hCaptcha service is down, log error but don't block login
            \Log::warning('hCaptcha verification failed due to service error', [
                'error' => $e->getMessage(),
                'ip' => $this->ip(),
            ]);
        }
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
