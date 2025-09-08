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
    <div class="position-fixed" style="left: 20px; top: 50%; transform: translateY(-50%); z-index: 1050;">
        <div class="bg-white rounded shadow-lg p-2">
    @else
    <!-- Regular Social Share -->
    <div class="d-flex align-items-center justify-content-center">
    @endif
    
    @if($style !== 'floating')
        <span class="{{ $theme === 'dark' ? 'text-white' : 'text-muted' }} fw-medium me-3">Share:</span>
    @endif
    
    @foreach($platforms as $platform)
        @if($platform === 'facebook')
            <a href="#" 
               x-on:click.prevent="window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl), 'facebook-share', 'width=600,height=400')"
               class="social-btn facebook btn d-inline-flex align-items-center justify-content-center rounded-circle text-white me-3"
               style="background-color: #1877f2; border: none; width: 45px; height: 45px; font-size: 18px;"
               title="Share on Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
        @endif
        
        @if($platform === 'twitter')
            <a href="#" 
               x-on:click.prevent="window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareTitle) + '&hashtags=' + hashtags, 'twitter-share', 'width=600,height=400')"
               class="social-btn twitter btn d-inline-flex align-items-center justify-content-center rounded-circle text-white me-3"
               style="background-color: #1da1f2; border: none; width: 45px; height: 45px; font-size: 18px;"
               title="Share on Twitter">
                <i class="fab fa-twitter"></i>
            </a>
        @endif
        
        @if($platform === 'linkedin')
            <a href="#" 
               x-on:click.prevent="window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl), 'linkedin-share', 'width=600,height=400')"
               class="social-btn linkedin btn d-inline-flex align-items-center justify-content-center rounded-circle text-white me-3"
               style="background-color: #0077b5; border: none; width: 45px; height: 45px; font-size: 18px;"
               title="Share on LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </a>
        @endif
        
        @if($platform === 'whatsapp')
            <a href="#" 
               x-on:click.prevent="window.open('https://wa.me/?text=' + encodeURIComponent(shareTitle + ' ' + shareUrl), 'whatsapp-share', 'width=600,height=400')"
               class="social-btn whatsapp btn d-inline-flex align-items-center justify-content-center rounded-circle text-white me-3"
               style="background-color: #25d366; border: none; width: 45px; height: 45px; font-size: 18px;"
               title="Share on WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
        @endif
        
        @if($platform === 'telegram')
            <a href="#" 
               x-on:click.prevent="window.open('https://t.me/share/url?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(shareTitle), 'telegram-share', 'width=600,height=400')"
               class="social-btn telegram btn d-inline-flex align-items-center justify-content-center rounded-circle text-white me-3"
               style="background-color: #0088cc; border: none; width: 45px; height: 45px; font-size: 18px;"
               title="Share on Telegram">
                <i class="fab fa-telegram-plane"></i>
            </a>
        @endif
        
        @if($platform === 'email')
            <a href="#" 
               x-on:click.prevent="window.location.href = 'mailto:?subject=' + encodeURIComponent(shareTitle) + '&body=' + encodeURIComponent(shareDescription + ' ' + shareUrl)"
               class="social-btn email btn d-inline-flex align-items-center justify-content-center rounded-circle text-white me-3"
               style="background-color: #6c757d; border: none; width: 45px; height: 45px; font-size: 18px;"
               title="Share via Email">
                <i class="fas fa-envelope"></i>
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
