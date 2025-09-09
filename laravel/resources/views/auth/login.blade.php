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
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
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
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

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

        let isOtpStep = false;
        const DEVICE_KEY = 'ai_chat_device_id';
        
        // Generate or get device ID
        function getDeviceId() {
            let deviceId = localStorage.getItem(DEVICE_KEY);
            if (!deviceId) {
                deviceId = 'device_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                localStorage.setItem(DEVICE_KEY, deviceId);
            }
            return deviceId;
        }

        // Check if device is trusted for this email
        function isDeviceTrusted(email) {
            const trustedDevices = JSON.parse(localStorage.getItem('trusted_devices') || '{}');
            const deviceId = getDeviceId();
            return trustedDevices[email] && trustedDevices[email].includes(deviceId);
        }

        // Mark device as trusted
        function markDeviceTrusted(email) {
            const trustedDevices = JSON.parse(localStorage.getItem('trusted_devices') || '{}');
            const deviceId = getDeviceId();
            if (!trustedDevices[email]) {
                trustedDevices[email] = [];
            }
            if (!trustedDevices[email].includes(deviceId)) {
                trustedDevices[email].push(deviceId);
                localStorage.setItem('trusted_devices', JSON.stringify(trustedDevices));
            }
        }

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

            if (!isOtpStep) {
                // Step 1: Try regular login first
                await attemptLogin(email, password);
            } else {
                // Step 2: Verify OTP and complete login
                if (!otp || otp.length !== 6) {
                    alert('Please enter a valid 6-digit OTP code');
                    return;
                }
                await verifyOTPAndLogin(email, password, otp);
            }
        });

        async function attemptLogin(email, password) {
            // If device is trusted, try normal login first
            if (isDeviceTrusted(email)) {
                try {
                    showSpinner(true);
                    
                    const formData = new FormData();
                    formData.append('email', email);
                    formData.append('password', password);
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        // Login successful - redirect
                        window.location.reload();
                        return;
                    }
                } catch (error) {
                    console.error('Direct login failed:', error);
                } finally {
                    showSpinner(false);
                }
            }
            
            // Device not trusted or login failed - request OTP
            await sendOTPAfterCredentialsCheck(email, password);
        }

        async function sendOTPAfterCredentialsCheck(email, password) {
            try {
                showSpinner(true);
                
                // First validate credentials without OTP
                const formData = new FormData();
                formData.append('email', email);
                formData.append('password', password);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                formData.append('check_credentials_only', '1'); // Flag to just check credentials

                const credentialsResponse = await fetch('/auth/check-credentials', {
                    method: 'POST',
                    body: formData
                });

                const credentialsData = await credentialsResponse.json();
                
                if (credentialsData.valid) {
                    // Credentials are valid - now send OTP
                    const response = await fetch('/auth/send-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ email: email })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        isOtpStep = true;
                        otpSection.style.display = 'block';
                        loginText.textContent = 'Verify & Login';
                        otpMessage.textContent = 'We\'ve sent a 6-digit code to ' + email + '. Please enter it below.';
                        otpInput.focus();
                    } else {
                        alert(data.message || 'Failed to send OTP. Please try again.');
                    }
                } else {
                    alert('Invalid email or password. Please check your credentials.');
                }
            } catch (error) {
                alert('Error validating credentials. Please try again.');
                console.error('Credentials check error:', error);
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

                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                if (response.ok) {
                    // Login successful - mark device as trusted
                    markDeviceTrusted(email);
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
                try {
                    const response = await fetch('/auth/send-otp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ email: email })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        this.textContent = 'OTP Sent!';
                        this.disabled = true;
                        setTimeout(() => {
                            this.textContent = 'Resend OTP';
                            this.disabled = false;
                        }, 30000); // Re-enable after 30 seconds
                    } else {
                        alert('Failed to resend OTP');
                    }
                } catch (error) {
                    alert('Error resending OTP');
                }
            }
        });
    });
    </script>
</x-guest-layout>
