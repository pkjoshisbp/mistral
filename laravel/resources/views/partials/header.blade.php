<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top" style="z-index: 1050;">
    <div class="container-fluid">
        <a class="navbar-brand text-white fw-bold" href="{{ route('home') }}" style="font-size:1.5rem;">
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
                    <a class="nav-link" href="{{ route('features') }}">{{ __('common.features') ?? 'Features' }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('home') }}#pricing">{{ __('common.pricing') ?? 'Pricing' }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('credits-and-services') }}">Credits & Services</a>
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
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('affiliate.register') }}">Become an Affiliate</a>
                </li>
            </ul>
            
            <ul class="navbar-nav align-items-center ms-auto">
                <li class="nav-item dropdown me-2">
                    @php($currentLocale = session('app_locale', app()->getLocale()))
                    <a class="nav-link dropdown-toggle text-white" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ strtoupper($currentLocale) }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                        @foreach(['en'=>'English','de'=>'Deutsch','fr'=>'Français','it'=>'Italiano','pt'=>'Português','hi'=>'हिन्दी','es'=>'Español','th'=>'ไทย'] as $code=>$label)
                            @php($currentPath = request()->getPathInfo())
                            @php($currentSegments = array_values(array_filter(explode('/', $currentPath))))
                            @php($availableLocales = ['de','fr','es','it','pt','hi','th'])
                            
                            @if($code === 'en')
                                @php($isLocalized = !empty($currentSegments) && in_array($currentSegments[0], $availableLocales))
                                @php($basePath = $isLocalized && count($currentSegments) > 1 ? '/' . implode('/', array_slice($currentSegments, 1)) : ($isLocalized ? '' : $currentPath))
                                @php($localizedPath = $basePath ?: '/')
                            @else
                                @php($isLocalized = !empty($currentSegments) && in_array($currentSegments[0], $availableLocales))
                                @php($basePath = $isLocalized && count($currentSegments) > 1 ? '/' . implode('/', array_slice($currentSegments, 1)) : ($isLocalized ? '' : $currentPath))
                                @php($localizedPath = '/' . $code . $basePath)
                            @endif
                            
                            <li><a class="dropdown-item" href="{{ url($localizedPath) }}">{{ $label }}</a></li>
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
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                            @elseif(Auth::user()->role === 'customer')
                                <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}">Customer Dashboard</a></li>
                            @elseif(Auth::user()->role === 'affiliate')
                                <li><a class="dropdown-item" href="{{ route('affiliate.dashboard') }}">Affiliate Dashboard</a></li>
                            @else
                                <li><a class="dropdown-item" href="{{ route('home') }}">Home</a></li>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
