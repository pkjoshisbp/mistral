<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

use App\Models\Blog;
use Illuminate\Support\Facades\App;

// Public Routes (no authentication required)
Route::get('/', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
})->name('home');

// Language switch
Route::get('/lang/{locale}', function ($locale) {
    $available = ['en','de','fr','it','pt','hi','es','th'];
    if (in_array($locale, $available)) {
        session(['app_locale' => $locale]);
    }
    return back();
})->name('lang.switch');

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
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
})->name('terms');

Route::get('/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
})->name('privacy');

Route::get('/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
})->name('refund-policy');

// Blog Routes

Route::get('/blog', function () {
    $blogs = Blog::published()->orderBy('published_at', 'desc')->paginate(6);
    return view('public.blog.index', compact('blogs'));
})->name('blog.index');


Route::get('/blog/{blog:slug}', function (Blog $blog) {
    // Get related posts (exclude current post)
    $relatedPosts = Blog::published()
        ->where('id', '!=', $blog->id)
        ->inRandomOrder()
        ->limit(3)
        ->get();
    return view('public.blog.show', compact('blog', 'relatedPosts'));
})->name('blog.show');

// SEO Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Analytics tracking routes
Route::post('/analytics/track', [AnalyticsController::class, 'track'])->name('analytics.track');
Route::get('/analytics/dashboard/{orgId}', [AnalyticsController::class, 'dashboard'])->name('analytics.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Customer redirect
Route::get('/customer', function () {
    return redirect()->route('customer.dashboard');
})->name('customer.redirect');

// Admin redirect
Route::get('/admin', function () {
    return redirect()->route('admin.dashboard');
})->name('admin.redirect');

// Admin Routes (for system administrators)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');
    
    Route::get('/organizations', function () {
        return view('organizations');
    })->name('organizations');
    
    Route::get('/website-crawler', function () {
        return view('website-crawler');
    })->name('website-crawler');
    
    // Data entry routes
    Route::get('/data-entry', function () {
        return view('admin.data-entry');
    })->name('data-entry');
    
    Route::get('/data-entry-manager', \App\Livewire\Admin\DataEntry::class)->name('data-entry-manager');
    Route::get('/data-entry-advanced', \App\Livewire\DataEntryManager::class)->name('data-entry-advanced');
    
    // Removed old Manual Data Entry route. Use dedicated child pages for each data type.
    
    Route::get('/ai-chat', function () {
        return view('ai-chat');
    })->name('ai-chat');
    
    // Debug routes for troubleshooting AI (commented out - controller missing)
    // Route::get('/debug/collections', [App\Http\Controllers\DebugController::class, 'checkCollections'])->name('debug.collections');
    // Route::get('/debug/search', [App\Http\Controllers\DebugController::class, 'testSearch'])->name('debug.search');
    
    Route::get('/widget-manager', function () {
        return view('widget-manager');
    })->name('widget-manager');
    
    Route::get('/api-endpoints', function () {
        return view('api-endpoints');
    })->name('api-endpoints');
    
    Route::get('/invoices', function () {
        return view('admin.invoices');
    })->name('invoices');
    
    Route::get('/invoices/{invoice}/pdf', function (\App\Models\Invoice $invoice) {
        $invoiceService = new \App\Services\InvoiceService();
        $pdfContent = $invoiceService->generatePDF($invoice);
        
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $invoice->invoice_number . '.pdf"',
        ]);
    })->name('invoices.pdf');
    
    // Test route for Indian pricing
    Route::get('/test-india', function () {
        session(['force_india' => true]);
        return redirect('/');
    })->name('test.india');
    
    Route::get('/test-us', function () {
        session()->forget('force_india');
        return redirect('/');
    })->name('test.us');
    
    Route::get('/users', function () {
        return view('admin.users');
    })->name('users');
    
    Route::get('/terms-management', function () {
        return view('admin.terms-management');
    })->name('terms-management');
    
    Route::get('/settings', \App\Livewire\Admin\SettingsManager::class)->name('settings');
    Route::get('/services', \App\Livewire\Admin\ServicesManager::class)->name('services');
    Route::get('/faqs', \App\Livewire\Admin\FaqsManager::class)->name('faqs');
    Route::get('/general-info', \App\Livewire\Admin\GeneralInfoManager::class)->name('general-info');
    Route::get('/documents', \App\Livewire\Admin\DocumentsManager::class)->name('documents');
    Route::get('/chat-history', \App\Livewire\Admin\ChatHistoryManager::class)->name('chat-history');
    Route::get('/leads', \App\Livewire\Admin\LeadsManager::class)->name('leads');
    Route::get('/analytics', \App\Livewire\Admin\AnalyticsDashboard::class)->name('analytics');
    
    // Profile routes for admin
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
        Route::get('/organization', \App\Livewire\Customer\OrganizationManager::class)->name('organization');
        Route::get('/services', \App\Livewire\Customer\Services::class)->name('services');
        Route::get('/faqs', \App\Livewire\Customer\Faqs::class)->name('faqs');
        Route::get('/general-info', \App\Livewire\Customer\GeneralInfo::class)->name('general-info');
        Route::get('/documents', \App\Livewire\Customer\Documents::class)->name('documents');
        Route::get('/website-crawler', \App\Livewire\Customer\WebsiteCrawler::class)->name('crawler');
        Route::get('/api-integration', \App\Livewire\Customer\ApiIntegration::class)->name('api-integration');
        Route::get('/chat-history', \App\Livewire\Customer\ChatHistory::class)->name('chat-history');
        Route::get('/leads', \App\Livewire\Customer\LeadsManager::class)->name('leads');
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
        Route::get('/crawler', function () {
            return view('customer.crawler');
        })->name('crawler');
        Route::get('/google-sheets', function () {
            return view('customer.google-sheets');
        })->name('google-sheets');
        Route::get('/widget', function () {
            return view('customer.widget');
        })->name('widget');
        Route::get('/whatsapp', function () {
            return view('customer.whatsapp');
        })->name('whatsapp');
        Route::get('/chat-test', function () {
            return view('customer.chat-test');
        })->name('chat-test');
        
        // Profile routes for customer
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

// Widget Routes (Public - no auth required)
Route::prefix('widget')->middleware([\App\Http\Middleware\CorsMiddleware::class, 'noindex'])->group(function () {
    Route::get('{orgId}/script.js', [\App\Http\Controllers\WidgetController::class, 'getWidgetScript'])->name('widget.script');
    Route::get('{orgId}/styles.css', [\App\Http\Controllers\WidgetController::class, 'getWidgetCSS'])->name('widget.styles');
    Route::post('{orgId}/chat', [\App\Http\Controllers\WidgetController::class, 'chat'])->name('widget.chat');
    Route::get('{orgId}/config', [\App\Http\Controllers\WidgetController::class, 'getConfig'])->name('widget.config');
    Route::get('{orgId}/test', function($orgId) {
        $organization = \App\Models\Organization::findOrFail($orgId);
        return view('widget.test', compact('organization'));
    })->name('widget.test');
});

// API Routes
Route::prefix('api')->middleware('noindex')->group(function () {
    // WhatsApp Webhook
    Route::get('/whatsapp/webhook', [\App\Http\Controllers\WhatsAppController::class, 'verifyWebhook']);
    Route::post('/whatsapp/webhook', [\App\Http\Controllers\WhatsAppController::class, 'handleWebhook']);
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
    
    // Test endpoint for webhook connectivity
    Route::post('webhook-test', function(\Illuminate\Http\Request $request) {
        \Log::info('Razorpay webhook test received', [
            'headers' => $request->headers->all(),
            'payload' => $request->getContent()
        ]);
        return response()->json(['status' => 'success', 'message' => 'Webhook endpoint is accessible']);
    })->name('webhook-test');
});

// OTP Routes
Route::post('/auth/send-otp', function(\Illuminate\Http\Request $request) {
    $request->validate([
        'email' => 'required|email|exists:users,email'
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    }

    // Check if device is trusted
    $deviceFingerprint = $request->header('X-Device-Fingerprint');
    if ($deviceFingerprint) {
        $trustedDevices = json_decode($request->cookie('trusted_devices', '[]'), true);
        if (in_array($deviceFingerprint, $trustedDevices)) {
            return response()->json([
                'success' => true,
                'trusted_device' => true,
                'message' => 'Device is trusted, no OTP required'
            ]);
        }
    }

    // Generate and send OTP
    $otpRecord = \App\Models\EmailOtp::generateForEmail($request->email, 'login');
    
    // Send email notification
    $user->notify(new \App\Notifications\OtpLoginNotification($otpRecord->otp));

    return response()->json([
        'success' => true,
        'trusted_device' => false,
        'message' => 'OTP sent successfully'
    ]);
})->name('auth.send-otp');

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Sitemap Routes for SEO
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', function () {
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

    // Static pages
    $pages = [
        ['url' => '', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => '/features', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => '/about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => '/contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => '/blog', 'priority' => '0.9', 'changefreq' => 'daily'],
    ];

    foreach ($pages as $page) {
        $sitemap .= '
    <url>
        <loc>' . config('app.url') . $page['url'] . '</loc>
        <changefreq>' . $page['changefreq'] . '</changefreq>
        <priority>' . $page['priority'] . '</priority>
        <lastmod>' . now()->toISOString() . '</lastmod>
    </url>';
    }

    // Blog posts
    $blogs = Blog::published()->get();
    foreach ($blogs as $blog) {
        $sitemap .= '
    <url>
        <loc>' . config('app.url') . '/blog/' . $blog->slug . '</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
        <lastmod>' . $blog->updated_at->toISOString() . '</lastmod>';
        
        if ($blog->featured_image) {
            $sitemap .= '
        <image:image>
            <image:loc>' . $blog->featured_image . '</image:loc>
            <image:title>' . htmlspecialchars($blog->title) . '</image:title>
        </image:image>';
        }
        
        $sitemap .= '
    </url>';
    }

    $sitemap .= '
</urlset>';

    return response($sitemap, 200)
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

Route::get('/robots.txt', function () {
    $robotsTxt = "User-agent: *
Allow: /

# Sitemap
Sitemap: " . config('app.url') . "/sitemap.xml

# Optimize crawling
Crawl-delay: 1

# Allow important directories
Allow: /blog/
Allow: /features/
Allow: /about/
Allow: /contact/

# Disallow admin areas
Disallow: /admin/
Disallow: /dashboard/
Disallow: /livewire/
Disallow: /_debugbar/
Disallow: /telescope/

# Disallow file types
Disallow: *.json
Disallow: *.xml$
Disallow: *.txt$
";

    return response($robotsTxt, 200)
        ->header('Content-Type', 'text/plain');
})->name('robots');

// Helper route to stash intended payment in session before login
Route::post('/persist-selected-plan', function(\Illuminate\Http\Request $request) {
    $request->validate([
        'plan_id' => 'required|integer',
        'provider' => 'required|in:paypal,razorpay',
        'billing_cycle' => 'nullable|in:monthly,yearly',
    ]);
    session([
        'selected_plan_id' => $request->input('plan_id'),
        'payment_provider' => $request->input('provider'),
        'billing_cycle' => $request->input('billing_cycle', 'monthly'),
    ]);
    return response()->json(['ok' => true]);
})->name('persist-selected-plan');
