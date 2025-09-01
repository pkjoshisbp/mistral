<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand text-gradient" href="{{ route('home') }}">
            <i class="fas fa-robot me-2"></i>AI Chat Support
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('home') }}">{{ __('common.home') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('about') }}">{{ __('common.about') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('blog.index') }}">{{ __('common.blog') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('contact') }}">{{ __('common.contact') }}</a>
                </li>
            </ul>
            
            <ul class="navbar-nav align-items-center">
                <li class="nav-item dropdown me-2">
                    @php($currentLocale = session('app_locale', app()->getLocale()))
                    <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown">
                        {{ strtoupper($currentLocale) }}
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="langDropdown">
                        @foreach(['en'=>'English','de'=>'Deutsch','fr'=>'Français','it'=>'Italiano','pt'=>'Português','hi'=>'हिन्दी','es'=>'Español','th'=>'ไทย'] as $code=>$label)
                            <li><a class="dropdown-item" href="{{ route('lang.switch', $code) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </li>
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu">
                            @if(Auth::user()->role === 'admin')
                                <li><a class="nav-link" href="{{ route('dashboard') }}">Admin Dashboard</a></li>
                            @else
                                <li><a class="nav-link" href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Logout</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">{{ __('common.login') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2" href="{{ route('register') }}">{{ __('common.get_started') }}</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
