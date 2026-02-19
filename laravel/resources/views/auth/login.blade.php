<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />
    @if (session('error'))
        <div class="mb-4 text-sm text-red-600">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->has('auth') || $errors->has('email') || $errors->has('password') || $errors->has('otp'))
        <div class="mb-4 text-sm text-red-600">
            {{ $errors->first('auth') ?: $errors->first('email') ?: $errors->first('password') ?: $errors->first('otp') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-2">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <x-input-label for="email" :value="__('auth.email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div class="mb-3">
            <x-input-label for="password" :value="__('auth.password')" />
            <div class="position-relative">
                <x-text-input id="password" class="pe-5"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
                <button type="button" id="toggle-password" class="btn btn-link position-absolute top-0 end-0 mt-1 me-1 p-1 text-decoration-none">
                    <svg id="eye-open" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5c-4.478 0-8.268 2.943-9.542 7 1.274 4.057 5.064 7 9.542 7 4.478 0 8.268-2.943 9.542-7C20.268 7.943 16.478 5 12 5zm0 10a3 3 0 110-6 3 3 0 010 6z"></path>
                    </svg>
                    <svg id="eye-closed" width="20" height="20" class="d-none" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 3l18 18-1.5 1.5L15.6 18.6A10.5 10.5 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029l2.33 2.33A7.5 7.5 0 004.5 12c1.274 4.057 5.064 7 9.542 7 .559 0 1.11-.04 1.65-.118L1.5 4.5 3 3z"></path>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Remember Me -->
        <div class="form-check mb-3">
            <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
            <label for="remember_me" class="form-check-label">{{ __('auth.remember_me') }}</label>
        </div>

        <!-- OTP Section -->
    <div id="otp-section" class="mb-3" style="display: {{ $errors->has('otp') ? 'block' : 'none' }};">
            <div class="mb-2">
                <x-input-label for="otp" :value="__('auth.enter_otp_code')" />
                <x-text-input id="otp" type="text" name="otp" maxlength="6" placeholder="{{ __('auth.enter_otp_code') }}" @if(!$errors->has('otp')) disabled @endif />
                <x-input-error :messages="$errors->get('otp')" />
            </div>
            <div class="text-muted small mb-2">
                <span id="otp-message">{{ __('auth.otp_sent_message') }}</span>
                <button type="button" id="resend-otp" class="btn btn-link p-0 ms-2">{{ __('auth.resend_otp') }}</button>
            </div>
            
            <!-- Remember Device for OTP -->
            <div class="form-check">
                <input id="remember_device" type="checkbox" class="form-check-input" name="remember_device">
                <label for="remember_device" class="form-check-label">{{ __('auth.remember_device') }}</label>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-3">
            @if (Route::has('password.request'))
                <a class="text-decoration-underline small" href="{{ route('password.request') }}">
                    {{ __('auth.forgot_password') }}
                </a>
            @endif

            

            <x-primary-button id="login-button" class="ms-3">
                <span id="login-text">{{ __('auth.log_in') }}</span>
                <span id="login-spinner" class="d-none ms-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                </span>
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
                <span class="text-muted">{{ __('auth.support_email') }} <a href="mailto:info@ai-chat.support" class="text-decoration-underline">info@ai-chat.support</a></span>
            </div>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const otpSection = document.getElementById('otp-section');
        const otpInput = document.getElementById('otp');
        const loginButton = document.getElementById('login-button');
        const loginText = document.getElementById('login-text');
        const loginSpinner = document.getElementById('login-spinner');
        const resendBtn = document.getElementById('resend-otp');
        const otpMessage = document.getElementById('otp-message');

    // We no longer track client-side OTP step; rely on server to decide

        // Generate device fingerprint
        function generateDeviceFingerprint() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillText('Device fingerprint', 2, 2);
            
            const fingerprint = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                canvas.toDataURL()
            ].join('|');
            
            // Simple hash function
            let hash = 0;
            for (let i = 0; i < fingerprint.length; i++) {
                const char = fingerprint.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32-bit integer
            }
            return Math.abs(hash).toString(36);
        }

        const deviceFingerprint = generateDeviceFingerprint();
        // Attach device fingerprint to the form for native submission
        (function(){
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'device_fingerprint';
            hidden.value = deviceFingerprint;
            form.appendChild(hidden);
        })();

        // Helper to set/update hidden inputs on the form
        function setHiddenField(name, value) {
            let input = form.querySelector('input[type="hidden"][name="' + name + '"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }
            input.value = value;
        }

        // Handle form submission: always submit natively first so server validates credentials before OTP
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = emailInput.value;
            const password = passwordInput.value;
            const otp = otpInput.value;

            if (!email || !password) {
                alert('Please enter both email and password.');
                return;
            }

            // If OTP section is visible (server indicated OTP required), require OTP
            const otpVisible = otpSection && getComputedStyle(otpSection).display !== 'none';
            if (otpVisible) {
                if (!otp || otp.length !== 6) {
                    alert('Please enter a valid 6-digit OTP code');
                    return;
                }
                setHiddenField('otp', otp);
                const rememberDevice = document.getElementById('remember_device');
                if (rememberDevice && rememberDevice.checked) {
                    setHiddenField('remember_device', '1');
                }
            }
            showSpinner(true);
            form.submit();
        });

        async function sendOTP(email) {
            try {
                showSpinner(true);
                const response = await fetch('/auth/send-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Device-Fingerprint': deviceFingerprint
                    },
                    body: JSON.stringify({ email: email })
                });

                const data = await response.json();
                
                if (data.success) {
                    // If requested from Resend button, notify user
                    otpMessage.textContent = 'We\'ve sent a 6-digit code to ' + email + '. Please enter it below.';
                    // Keep UI state as determined by server
                } else {
                    alert(data.message || 'Failed to send OTP. Please try again.');
                }
            } catch (error) {
                alert('Error sending OTP. Please try again.');
                console.error('OTP Error:', error);
            } finally {
                showSpinner(false);
            }
        }

        // verifyOTPAndLogin no longer used; native submit handles errors via Laravel session

        function showSpinner(show) {
            if (show) {
                loginSpinner.classList.remove('d-none');
                loginButton.disabled = true;
            } else {
                loginSpinner.classList.add('d-none');
                loginButton.disabled = false;
            }
        }

        // Resend OTP (only after server indicates OTP is required)
        resendBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            // Allow resend only if OTP section is visible (server requested)
            const otpVisible = otpSection && getComputedStyle(otpSection).display !== 'none';
            if (!otpVisible) {
                alert('Please submit your email and password first.');
                return;
            }
            const email = emailInput.value;
            if (email) {
                await sendOTP(email);
                this.textContent = 'OTP Sent!';
                this.disabled = true;
                setTimeout(() => {
                    this.textContent = 'Resend OTP';
                    this.disabled = false;
                }, 30000); // Re-enable after 30 seconds
            }
        });

        // Simple login (debug) removed; keep code safe if element is absent
        const simpleLoginBtn = document.getElementById('simple-login');
        if (simpleLoginBtn) {
            simpleLoginBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                const email = emailInput.value;
                const password = passwordInput.value;

                if (!email || !password) {
                    alert('Please enter both email and password.');
                    return;
                }

                try {
                    const response = await fetch('/auth/simple-login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ email: email, password: password})
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        console.log('Simple login successful, redirecting to:', data.redirect);
                        window.location.href = data.redirect;
                    } else {
                        alert(data.message || 'Login failed');
                        console.error('Simple login failed:', data);
                    }
                } catch (error) {
                    alert('Login error: ' + error.message);
                    console.error('Simple login error:', error);
                }
            });
        }

        // Password visibility toggle
        const togglePassword = document.getElementById('toggle-password');
        const eyeOpen = document.getElementById('eye-open');
        const eyeClosed = document.getElementById('eye-closed');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icons
            if (type === 'text') {
                eyeOpen.classList.add('d-none');
                eyeClosed.classList.remove('d-none');
            } else {
                eyeOpen.classList.remove('d-none');
                eyeClosed.classList.add('d-none');
            }
        });
    });
    </script>
</x-guest-layout>
