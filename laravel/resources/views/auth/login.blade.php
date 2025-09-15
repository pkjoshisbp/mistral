<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative">
                <x-text-input id="password" class="block mt-1 w-full pr-10"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
                <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700">
                    <svg id="eye-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    <svg id="eye-closed" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L12 12m6.022-.878L21 21m-3.978-3.978l-3.022-3.022m0 0L12 12m3-3L12 12m-3-3l-3 3m3-3l3 3"></path>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <!-- OTP Section -->
        <div id="otp-section" class="mt-4" style="display: none;">
            <div>
                <x-input-label for="otp" :value="__('Enter OTP Code')" />
                <x-text-input id="otp" class="block mt-1 w-full" type="text" name="otp" maxlength="6" placeholder="Enter 6-digit OTP" />
                <x-input-error :messages="$errors->get('otp')" class="mt-2" />
            </div>
            <div class="mt-2 text-sm text-gray-600">
                <span id="otp-message">We've sent a 6-digit code to your email address. Please check your inbox.</span>
                <button type="button" id="resend-otp" class="ml-2 text-indigo-600 hover:text-indigo-800 underline">
                    Resend OTP
                </button>
            </div>
            
            <!-- Remember Device for OTP -->
            <div class="mt-3">
                <label for="remember_device" class="inline-flex items-center">
                    <input id="remember_device" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember_device">
                    <span class="ms-2 text-sm text-gray-600">{{ __('Remember this device (skip OTP for 30 days)') }}</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <button type="button" id="simple-login" class="ms-3 inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Simple Login (Debug)
            </button>

            <x-primary-button id="login-button" class="ms-3">
                <span id="login-text">{{ __('Log In') }}</span>
                <span id="login-spinner" class="hidden ml-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </x-primary-button>
        </div>
        <!-- Trust & Security Section -->
        <div class="mt-6 text-center text-sm text-gray-500">
            <hr class="my-4">
            <div class="mb-2">
                <strong>ai-chat.support is owned and operated by MYWEB SOLUTIONS.</strong>
            </div>
            <div>
                <a href="{{ route('privacy') }}" class="me-3 underline">Privacy Policy</a>
                <a href="{{ route('terms') }}" class="me-3 underline">Terms of Service</a>
                <a href="{{ route('contact') }}" class="underline">Contact Us</a>
            </div>
            <div class="mt-2">
                <span class="text-xs">For support or security concerns, email <a href="mailto:info@ai-chat.support" class="underline">info@ai-chat.support</a></span>
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

        let otpSent = false;

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

        // Handle form submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = emailInput.value;
            const password = passwordInput.value;
            const otp = otpInput.value;

            if (!email || !password) {
                alert('Please enter both email and password.');
                return;
            }

            if (!otpSent) {
                // First step - always send OTP for security
                await sendOTP(email);
            } else {
                // Second step - verify OTP and complete login
                if (!otp || otp.length !== 6) {
                    alert('Please enter a valid 6-digit OTP code');
                    return;
                }
                await verifyOTPAndLogin(email, password, otp);
            }
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
                    if (data.trusted_device) {
                        // Device is trusted, proceed directly to login
                        const formData = new FormData();
                        formData.append('email', email);
                        formData.append('password', passwordInput.value);
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                        const loginResponse = await fetch(form.action, {
                            method: 'POST',
                            body: formData
                        });

                        if (loginResponse.ok) {
                            window.location.reload();
                        } else {
                            alert('Login failed. Please try again.');
                        }
                    } else {
                        // OTP required
                        otpSent = true;
                        otpSection.style.display = 'block';
                        loginText.textContent = 'Verify & Login';
                        otpMessage.textContent = 'We\'ve sent a 6-digit code to ' + email + '. Please enter it below.';
                        otpInput.focus();
                    }
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

        async function verifyOTPAndLogin(email, password, otp) {
            try {
                showSpinner(true);
                
                const formData = new FormData();
                formData.append('email', email);
                formData.append('password', password);
                formData.append('otp', otp);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                
                // Add remember device if checked
                const rememberDevice = document.getElementById('remember_device');
                if (rememberDevice && rememberDevice.checked) {
                    formData.append('remember_device', '1');
                }

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Device-Fingerprint': deviceFingerprint
                    },
                    body: formData
                });

                if (response.ok) {
                    // Login successful
                    window.location.reload();
                } else {
                    const data = await response.json();
                    if (data.errors) {
                        if (data.errors.otp) {
                            alert(data.errors.otp[0]);
                        } else if (data.errors.email) {
                            alert(data.errors.email[0]);
                        } else if (data.errors.password) {
                            alert(data.errors.password[0]);
                        } else {
                            alert('Login failed. Please check your credentials.');
                        }
                    } else {
                        alert('Login failed. Please try again.');
                    }
                }
            } catch (error) {
                alert('Error during login. Please try again.');
                console.error('Login Error:', error);
            } finally {
                showSpinner(false);
            }
        }

        function showSpinner(show) {
            if (show) {
                loginSpinner.classList.remove('hidden');
                loginButton.disabled = true;
            } else {
                loginSpinner.classList.add('hidden');
                loginButton.disabled = false;
            }
        }

        // Resend OTP
        resendBtn.addEventListener('click', async function(e) {
            e.preventDefault();
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

        // Simple login (for debugging)
        document.getElementById('simple-login').addEventListener('click', async function(e) {
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

        // Password visibility toggle
        const togglePassword = document.getElementById('toggle-password');
        const eyeOpen = document.getElementById('eye-open');
        const eyeClosed = document.getElementById('eye-closed');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icons
            if (type === 'text') {
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        });
    });
    </script>
</x-guest-layout>
