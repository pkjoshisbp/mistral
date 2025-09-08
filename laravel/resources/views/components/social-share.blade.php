@props([
    'url' => '',
    'title' => '',
    'description' => '',
    'image' => '',
    'hashtags' => '',
    'size' => 'normal', // 'small', 'normal', 'large'
    'style' => 'buttons', // 'buttons', 'icons', 'floating'
    'platforms' => ['facebook', 'twitter', 'linkedin', 'whatsapp', 'telegram', 'email'],
    'theme' => 'light' // 'light', 'dark'
])

@php
    $currentUrl = $url ?: url()->current();
    $shareTitle = $title ?: (isset($pageTitle) ? $pageTitle : config('app.name'));
    $shareDescription = $description ?: (isset($pageDescription) ? $pageDescription : 'Check out this amazing content!');
    $shareImage = $image ?: asset('images/ai-chat-support-banner.jpg');
    $shareHashtags = $hashtags ?: 'AIChat,CustomerSupport,Automation';
    
    $sizeClasses = [
        'small' => 'h-8 w-8 text-sm',
        'normal' => 'h-10 w-10 text-base',
        'large' => 'h-12 w-12 text-lg'
    ];
    
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['normal'];
@endphp

<div class="social-share {{ $style === 'floating' ? 'floating-share' : '' }}" 
     x-data="{ 
         shareUrl: '{{ $currentUrl }}',
         shareTitle: '{{ addslashes($shareTitle) }}',
         shareDescription: '{{ addslashes($shareDescription) }}',
         shareImage: '{{ $shareImage }}',
         hashtags: '{{ $shareHashtags }}'
     }">

<style>
.social-share a {
    text-decoration: none !important;
}
.social-share a:hover {
    text-decoration: none !important;
}
</style>
    
    @if($style === 'floating')
    <!-- Floating Social Share -->
    <div class="fixed left-4 top-1/2 transform -translate-y-1/2 z-50 space-y-2">
        <div class="bg-white rounded-full shadow-lg p-2 transition-all duration-300 hover:shadow-xl">
    @else
    <!-- Regular Social Share -->
    <div class="flex items-center justify-center {{ $style === 'icons' ? 'space-x-2' : 'space-x-3' }}">
    @endif
    
    @if($style !== 'floating')
        <span class="{{ $theme === 'dark' ? 'text-white' : 'text-gray-600' }} font-medium text-sm mr-2">Share:</span>
    @endif
    
    @foreach($platforms as $platform)
        @if($platform === 'facebook')
            <a href="#" 
               x-on:click.prevent="window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl), 'facebook-share', 'width=600,height=400')"
               class="social-btn facebook {{ $sizeClass }} inline-flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 transition-all duration-300 hover:scale-110 hover:shadow-lg"
               title="Share on Facebook">
                <i class="fab fa-facebook-f"></i>
                @if($style === 'buttons')
                    <span class="ml-2 hidden sm:inline">Facebook</span>
                @endif
            </a>
        @endif
        
        @if($platform === 'twitter')
            <a href="#" 
               x-on:click.prevent="window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareTitle) + '&hashtags=' + hashtags, 'twitter-share', 'width=600,height=400')"
               class="social-btn twitter {{ $sizeClass }} inline-flex items-center justify-center rounded-full bg-sky-500 text-white hover:bg-sky-600 transition-all duration-300 hover:scale-110 hover:shadow-lg"
               title="Share on Twitter">
                <i class="fab fa-twitter"></i>
                @if($style === 'buttons')
                    <span class="ml-2 hidden sm:inline">Twitter</span>
                @endif
            </a>
        @endif
        
        @if($platform === 'linkedin')
            <a href="#" 
               x-on:click.prevent="window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl), 'linkedin-share', 'width=600,height=400')"
               class="social-btn linkedin {{ $sizeClass }} inline-flex items-center justify-center rounded-full bg-blue-700 text-white hover:bg-blue-800 transition-all duration-300 hover:scale-110 hover:shadow-lg"
               title="Share on LinkedIn">
                <i class="fab fa-linkedin-in"></i>
                @if($style === 'buttons')
                    <span class="ml-2 hidden sm:inline">LinkedIn</span>
                @endif
            </a>
        @endif
        
        @if($platform === 'whatsapp')
            <a href="#" 
               x-on:click.prevent="window.open('https://wa.me/?text=' + encodeURIComponent(shareTitle + ' ' + shareUrl), 'whatsapp-share', 'width=600,height=400')"
               class="social-btn whatsapp {{ $sizeClass }} inline-flex items-center justify-center rounded-full bg-green-500 text-white hover:bg-green-600 transition-all duration-300 hover:scale-110 hover:shadow-lg"
               title="Share on WhatsApp">
                <i class="fab fa-whatsapp"></i>
                @if($style === 'buttons')
                    <span class="ml-2 hidden sm:inline">WhatsApp</span>
                @endif
            </a>
        @endif
        
        @if($platform === 'telegram')
            <a href="#" 
               x-on:click.prevent="window.open('https://t.me/share/url?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareTitle), 'telegram-share', 'width=600,height=400')"
               class="social-btn telegram {{ $sizeClass }} inline-flex items-center justify-center rounded-full bg-blue-500 text-white hover:bg-blue-600 transition-all duration-300 hover:scale-110 hover:shadow-lg"
               title="Share on Telegram">
                <i class="fab fa-telegram-plane"></i>
                @if($style === 'buttons')
                    <span class="ml-2 hidden sm:inline">Telegram</span>
                @endif
            </a>
        @endif
        
        @if($platform === 'email')
            <a href="#" 
               x-on:click.prevent="window.location.href = 'mailto:?subject=' + encodeURIComponent(shareTitle) + '&body=' + encodeURIComponent(shareDescription + ' ' + shareUrl)"
               class="social-btn email {{ $sizeClass }} inline-flex items-center justify-center rounded-full bg-gray-600 text-white hover:bg-gray-700 transition-all duration-300 hover:scale-110 hover:shadow-lg"
               title="Share via Email">
                <i class="fas fa-envelope"></i>
                @if($style === 'buttons')
                    <span class="ml-2 hidden sm:inline">Email</span>
                @endif
            </a>
        @endif
    @endforeach
    
    @if($style === 'floating')
        </div>
    </div>
    @else
    </div>
    @endif
</div>

@if($style === 'floating')
<style>
    .floating-share {
        z-index: 1000;
    }
    
    @media (max-width: 768px) {
        .floating-share {
            display: none;
        }
    }
</style>
@endif

<style>
    .social-share .social-btn {
        position: relative;
        overflow: hidden;
    }
    
    .social-share .social-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: width 0.3s, height 0.3s;
    }
    
    .social-share .social-btn:hover::before {
        width: 100%;
        height: 100%;
    }
    
    .social-share .facebook:hover {
        box-shadow: 0 8px 25px rgba(24, 119, 242, 0.4);
    }
    
    .social-share .twitter:hover {
        box-shadow: 0 8px 25px rgba(29, 161, 242, 0.4);
    }
    
    .social-share .linkedin:hover {
        box-shadow: 0 8px 25px rgba(0, 119, 181, 0.4);
    }
    
    .social-share .whatsapp:hover {
        box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
    }
    
    .social-share .telegram:hover {
        box-shadow: 0 8px 25px rgba(54, 165, 225, 0.4);
    }
    
    .social-share .email:hover {
        box-shadow: 0 8px 25px rgba(75, 85, 99, 0.4);
    }
</style>
