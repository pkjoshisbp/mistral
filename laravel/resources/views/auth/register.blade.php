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

        <!-- hCaptcha -->
        @if(config('services.hcaptcha.site_key'))
        <div class="mt-4">
            <div class="h-captcha" data-sitekey="{{ config('services.hcaptcha.site_key') }}"></div>
            <x-input-error :messages="$errors->get('h-captcha-response')" class="mt-2" />
        </div>
        @endif

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
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
    </form>
</x-guest-layout>
