<x-guest-layout>
    @if(request('plan'))
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <svg width="20" height="20" class="me-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <span>
                    {{ __('auth.selected_plan') }}: {{ ucfirst(request('plan')) }} 
                    @if(request('plan') == 'starter')
                        ($49/month)
                    @elseif(request('plan') == 'pro')
                        ($199/month)
                    @endif
                </span>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="mt-2">
        @csrf

        <!-- Hidden field for selected plan -->
        @if(request('plan'))
            <input type="hidden" name="plan" value="{{ request('plan') }}">
        @endif

        <!-- Name -->
        <div class="mb-3">
            <x-input-label for="name" :value="__('auth.name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <!-- Email Address -->
        <div class="mb-3">
            <x-input-label for="email" :value="__('auth.email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div class="mb-3">
            <x-input-label for="password" :value="__('auth.password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Confirm Password -->
        <div class="mb-3">
            <x-input-label for="password_confirmation" :value="__('auth.confirm_password')" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <div id="password-mismatch" class="invalid-feedback d-none">{{ __('auth.passwords_must_match') }}</div>
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <!-- OTP Section -->
        <div class="mb-3" id="otp-section" style="display: none;">
            <x-input-label for="otp" :value="__('auth.email_verification_code')" />
            <div class="d-flex gap-2">
                <x-text-input id="otp" type="text" name="otp" maxlength="6" placeholder="{{ __('auth.email_verification_code') }}" />
                <button type="button" id="resend-otp" class="btn btn-secondary btn-sm">{{ __('auth.resend') }}</button>
            </div>
            <x-input-error :messages="$errors->get('otp')" />
            <div id="otp-status" class="mt-1 small"></div>
        </div>

        <!-- Email Verification Button -->
        <div class="mb-3" id="verify-email-container" style="display: block !important; visibility: visible !important;">
            <button type="button" id="verify-email" class="btn btn-info w-100" style="display: block !important; visibility: visible !important;">
                {{ __('auth.verify_email') }}
            </button>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-3">
            <a class="text-decoration-underline small" href="{{ route('login') }}">
                {{ __('auth.already_registered') }}
            </a>

            <x-primary-button class="ms-4" id="register-button" disabled>
                {{ __('auth.register') }}
            </x-primary-button>
        </div>
            <!-- Trust & Security Section -->
            <div class="mt-4 text-center small text-muted">
                <hr class="my-3">
                <div class="mb-2">
                    <strong>{{ __('auth.owned_by') }}</strong>
                </div>
                <div>
                    <a href="{{ route('privacy') }}" class="me-3 text-decoration-underline">{{ __('auth.privacy_policy') }}</a>
                    <a href="{{ route('terms') }}" class="me-3 text-decoration-underline">{{ __('auth.terms_of_service') }}</a>
                    <a href="{{ route('contact') }}" class="text-decoration-underline">{{ __('auth.contact_us') }}</a>
                </div>
                <div class="mt-2">
                    <span>{{ __('auth.support_email') }} <a href="mailto:info@ai-chat.support" class="text-decoration-underline">info@ai-chat.support</a></span>
                </div>
            </div>

            <!-- Hidden field to track OTP verification status -->
            <input type="hidden" name="has_otp" id="has-otp" value="false">
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Client-side password confirmation validation
        const pass = document.getElementById('password');
        const pass2 = document.getElementById('password_confirmation');
        const mismatch = document.getElementById('password-mismatch');
        const registerBtn = document.getElementById('register-button');

        function validatePasswords() {
            if (!pass || !pass2) return true;
            const ok = pass.value !== '' && pass.value === pass2.value;
            if (!ok) {
                pass2.classList.add('is-invalid');
                if (mismatch) mismatch.classList.remove('d-none');
            } else {
                pass2.classList.remove('is-invalid');
                if (mismatch) mismatch.classList.add('d-none');
            }
            return ok;
        }

        ['input','blur'].forEach(evt => {
            if (pass2) pass2.addEventListener(evt, validatePasswords);
            if (pass) pass.addEventListener(evt, validatePasswords);
        });

        const verifyEmailBtn = document.getElementById('verify-email');
        const otpSection = document.getElementById('otp-section');
        const registerButton = document.getElementById('register-button');
        const resendOtpBtn = document.getElementById('resend-otp');
        const otpInput = document.getElementById('otp');
        const emailInput = document.getElementById('email');
        const hasOtpInput = document.getElementById('has-otp');
        const otpStatus = document.getElementById('otp-status');

        // Ensure register button starts disabled
        if (registerButton) {
            registerButton.disabled = true;
        }

        // Ensure verify email button is visible
        if (verifyEmailBtn) {
            verifyEmailBtn.style.display = 'block';
            verifyEmailBtn.style.visibility = 'visible';
        }

        let otpSent = false;
        let otpVerified = false;

        // For registration, we ALWAYS require OTP verification
        localStorage.removeItem('registerOtpVerified');

        verifyEmailBtn.addEventListener('click', function() {
            const email = emailInput.value;
            
            if (!email || !isValidEmail(email)) {
                showOtpStatus('Please enter a valid email address.', 'error');
                return;
            }

            sendOtp(email);
        });

        resendOtpBtn.addEventListener('click', function() {
            const email = emailInput.value;
            if (email && isValidEmail(email)) {
                sendOtp(email);
            }
        });

        otpInput.addEventListener('input', function() {
            if (this.value.length === 6 && /^\d{6}$/.test(this.value)) {
                // Auto-verify when 6 digits are entered
                setTimeout(() => {
                    otpVerified = true;
                    hasOtpInput.value = 'true';
                    registerButton.disabled = false;
                    registerButton.textContent = 'Register';
                    showOtpStatus('Email verified ✓', 'success');
                    
                    // Store verification in localStorage
                    localStorage.setItem('registerOtpVerified', JSON.stringify({
                        email: emailInput.value,
                        timestamp: Date.now()
                    }));
                }, 100);
            }
        });

        function sendOtp(email) {
            verifyEmailBtn.disabled = true;
            verifyEmailBtn.textContent = 'Sending...';
            showOtpStatus('Sending verification code...', 'info');

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value;

            if (!csrfToken) {
                showOtpStatus('Security token missing. Please refresh the page.', 'error');
                verifyEmailBtn.disabled = false;
                verifyEmailBtn.textContent = 'Verify Email Address';
                return;
            }

            fetch('/auth/send-registration-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    otpSent = true;
                    otpSection.style.display = 'block';
                    verifyEmailBtn.style.display = 'none';
                    showOtpStatus('Verification code sent to your email. Please check your inbox.', 'success');
                    otpInput.focus();
                } else {
                    showOtpStatus(data.message || 'Failed to send OTP. Please try again.', 'error');
                    verifyEmailBtn.disabled = false;
                    verifyEmailBtn.textContent = 'Verify Email Address';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showOtpStatus('Network error. Please check your internet connection and try again.', 'error');
                verifyEmailBtn.disabled = false;
                verifyEmailBtn.textContent = 'Verify Email Address';
            });
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function showOtpStatus(message, type) {
            otpStatus.textContent = message;
            otpStatus.className = `mt-1 text-sm ${
                type === 'success' ? 'text-green-600' : 
                type === 'error' ? 'text-red-600' : 
                'text-blue-600'
            }`;
        }

        // Form submission handler
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            if (!validatePasswords()) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password correctly.');
                return false;
            }
            if (!otpVerified && hasOtpInput.value !== 'true') {
                e.preventDefault();
                alert('Please verify your email address before registering.');
                return false;
            }
            
            if (otpVerified) {
                localStorage.removeItem('registerOtpVerified');
            }
        });
    });
    </script>
</x-guest-layout>
