<section>
    <header class="mb-3">
        <h4>{{ __('Profile Information') }}</h4>
        <p class="text-muted">{{ __("Update your account's profile information and email address.") }}</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ auth()->user()->role === 'admin' ? route('admin.profile.update') : route('customer.profile.update') }}">
        @csrf
        @method('patch')

        <div class="form-group">
            <label for="name">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            @error('name')
                <small class="text-danger">{{ $message }}</small>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required autocomplete="username" />
            @error('email')
                <small class="text-danger">{{ $message }}</small>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <small class="text-warning">
                        {{ __('Your email address is unverified.') }}
                        <button form="send-verification" type="submit" class="btn btn-link p-0 text-warning" style="text-decoration: underline;">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </small>

                    @if (session('status') === 'verification-link-sent')
                        <small class="text-success d-block mt-1">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </small>
                    @endif
                </div>
            @endif
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            
            @if (session('status') === 'profile-updated')
                <span class="text-success ml-2">{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>
</section>
