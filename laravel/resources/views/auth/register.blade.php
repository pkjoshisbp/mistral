<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- OTP Section -->
        <div class="mt-4" id="otp-section" style="display: none;">
            <x-input-label for="otp" :value="__('Email Verification Code')" />
            <div class="flex space-x-2">
                <x-text-input id="otp" class="block mt-1 w-full" type="text" name="otp" maxlength="6" placeholder="Enter 6-digit code" />
                <button type="button" id="resend-otp" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
                    Resend
                </button>
            </div>
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
            <div id="otp-status" class="mt-1 text-sm"></div>
        </div>

        <!-- Email Verification Button -->
        <div class="mt-4">
            <button type="button" id="verify-email" class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Verify Email Address
            </button>
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4" id="register-button" disabled>
                {{ __('Register') }}
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

            <!-- Hidden field to track OTP verification status -->
            <input type="hidden" name="has_otp" id="has-otp" value="false">
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const verifyEmailBtn = document.getElementById('verify-email');
        const otpSection = document.getElementById('otp-section');
        const registerButton = document.getElementById('register-button');
        const resendOtpBtn = document.getElementById('resend-otp');
        const otpInput = document.getElementById('otp');
        const emailInput = document.getElementById('email');
        const hasOtpInput = document.getElementById('has-otp');
        const otpStatus = document.getElementById('otp-status');

        let otpSent = false;
        let otpVerified = false;

        // Check if OTP is already verified from localStorage
        const storedOtpData = localStorage.getItem('registerOtpVerified');
        if (storedOtpData) {
            const otpData = JSON.parse(storedOtpData);
            const currentEmail = emailInput.value || '';
            
            if (otpData.email === currentEmail && otpData.timestamp > Date.now() - 10 * 60 * 1000) {
                // OTP is still valid (within 10 minutes)
                otpVerified = true;
                hasOtpInput.value = 'true';
                registerButton.disabled = false;
                registerButton.textContent = 'Register';
                verifyEmailBtn.style.display = 'none';
                otpStatus.textContent = 'Email verified ✓';
                otpStatus.className = 'mt-1 text-sm text-green-600';
            }
        }

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

            fetch('/auth/send-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
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
                showOtpStatus('Network error. Please try again.', 'error');
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

        // Clear localStorage on successful registration
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            if (otpVerified) {
                localStorage.removeItem('registerOtpVerified');
            }
        });
    });
    </script>
</x-guest-layout>
