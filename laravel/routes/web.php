<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Public Routes (no authentication required)
Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return view('public.about');
})->name('about');

Route::get('/features', function () {
    return view('public.features');
})->name('features');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

Route::get('/terms', function () {
    $terms = App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
})->name('terms');

Route::get('/privacy', function () {
    $privacy = App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
})->name('privacy');

Route::get('/refund-policy', function () {
    $refund = App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
})->name('refund-policy');

// Blog Routes
Route::get('/blog', function () {
    $blogs = App\Models\Blog::published()->orderBy('published_at', 'desc')->paginate(6);
    return view('public.blog.index', compact('blogs'));
})->name('blog.index');

Route::get('/blog/{blog:slug}', function (App\Models\Blog $blog) {
    // Get related posts (exclude current post)
    $relatedPosts = App\Models\Blog::published()
        ->where('id', '!=', $blog->id)
        ->inRandomOrder()
        ->limit(3)
        ->get();
    
    return view('public.blog.show', compact('blog', 'relatedPosts'));
})->name('blog.show');

// SEO Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Customer redirect
Route::get('/customer', function () {
    return redirect()->route('customer.dashboard');
})->name('customer.redirect');

// Admin Routes (for system administrators)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');
    
    Route::get('/organizations', function () {
        return view('organizations');
    })->name('organizations');
    
    Route::get('/data-sync', function () {
        return view('data-sync');
    })->name('data-sync');
    
    Route::get('/website-crawler', function () {
        return view('website-crawler');
    })->name('website-crawler');
    
    Route::get('/documents', function () {
        return view('admin.documents');
    })->name('documents');
    
    Route::get('/ai-chat', function () {
        return view('ai-chat');
    })->name('ai-chat');
    
    // Debug routes for troubleshooting AI
    Route::get('/debug/collections', [App\Http\Controllers\DebugController::class, 'checkCollections'])->name('debug.collections');
    Route::get('/debug/search', [App\Http\Controllers\DebugController::class, 'testSearch'])->name('debug.search');
    
    Route::get('/widget-manager', function () {
        return view('widget-manager');
    })->name('widget-manager');
    
    Route::get('/api-endpoints', function () {
        return view('api-endpoints');
    })->name('api-endpoints');
    
    Route::get('/users', function () {
        return view('admin.users');
    })->name('users');
    
    Route::get('/terms-management', function () {
        return view('admin.terms-management');
    })->name('terms-management');
    
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('settings');
});

// Customer Routes (for customers to manage their organization data)
Route::middleware(['auth', 'customer'])->prefix('customer')->name('customer.')->group(function () {
    // Organization setup route (available without organization)
    Route::get('/setup-organization', function () {
        return view('customer.setup-organization');
    })->name('setup-organization');
    
    // All other customer routes require an organization
    Route::middleware(['user.has.organization'])->group(function () {
        Route::get('/dashboard', function () {
            $user = auth()->user();
            $organization = $user->organizations->first();
            
            // Get basic stats
            $totalChats = 0;
            $todayChats = 0;
            $dataSources = 0;
            $subscriptionStatus = 'Active';
            $recentChats = collect();
            
            if ($organization) {
                $totalChats = \App\Models\ChatConversation::where('organization_id', $organization->id)->count();
                $todayChats = \App\Models\ChatConversation::where('organization_id', $organization->id)
                    ->whereDate('created_at', today())->count();
                $dataSources = \App\Models\DataSource::where('organization_id', $organization->id)->count();
                $recentChats = \App\Models\ChatConversation::where('organization_id', $organization->id)
                    ->with('messages')
                    ->withCount('messages')
                    ->orderBy('last_activity_at', 'desc')
                    ->limit(10)
                    ->get();
            }
            
            return view('customer.dashboard', compact(
                'totalChats', 'todayChats', 'dataSources', 
                'subscriptionStatus', 'recentChats'
            ));
        })->name('dashboard');
        
        Route::get('/data-sources', \App\Livewire\Customer\DataSources::class)->name('data-sources');
        Route::get('/content', function () {
            return view('customer.content');
        })->name('content');
        Route::get('/analytics', function () {
            return view('customer.analytics');
        })->name('analytics');
        Route::get('/subscription', function () {
            return view('customer.subscription');
        })->name('subscription');
        Route::get('/settings', function () {
            return view('customer.settings');
        })->name('settings');
    });
});

// Widget Routes (Public - no auth required)
Route::prefix('widget')->middleware(\App\Http\Middleware\CorsMiddleware::class)->group(function () {
    Route::get('{orgId}/script.js', [\App\Http\Controllers\WidgetController::class, 'getWidgetScript'])->name('widget.script');
    Route::get('{orgId}/styles.css', [\App\Http\Controllers\WidgetController::class, 'getWidgetCSS'])->name('widget.styles');
    Route::post('{orgId}/chat', [\App\Http\Controllers\WidgetController::class, 'chat'])->name('widget.chat');
    Route::get('{orgId}/config', [\App\Http\Controllers\WidgetController::class, 'getConfig'])->name('widget.config');
    Route::get('{orgId}/test', function($orgId) {
        $organization = \App\Models\Organization::findOrFail($orgId);
        return view('widget.test', compact('organization'));
    })->name('widget.test');
});

// PayPal Routes
Route::prefix('paypal')->name('paypal.')->group(function () {
    Route::post('create-subscription', [\App\Http\Controllers\PayPalController::class, 'createSubscription'])
        ->middleware('auth')
        ->name('create-subscription');
    Route::get('success', [\App\Http\Controllers\PayPalController::class, 'handleSuccess'])->name('success');
    Route::get('cancel', [\App\Http\Controllers\PayPalController::class, 'handleCancel'])->name('cancel');
    Route::post('webhook', [\App\Http\Controllers\PayPalController::class, 'handleWebhook'])->name('webhook');
});

// Razorpay Routes
Route::prefix('razorpay')->name('razorpay.')->group(function () {
    Route::post('create-subscription', [\App\Http\Controllers\RazorpayController::class, 'createSubscription'])
        ->middleware('auth')
        ->name('create-subscription');
    Route::post('success', [\App\Http\Controllers\RazorpayController::class, 'handleSuccess'])->name('success');
    Route::post('failure', [\App\Http\Controllers\RazorpayController::class, 'handleFailure'])->name('failure');
    Route::post('webhook', [\App\Http\Controllers\RazorpayController::class, 'handleWebhook'])->name('webhook');
});

require __DIR__.'/auth.php';
