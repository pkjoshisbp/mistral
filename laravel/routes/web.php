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

Route::get('/about', function () {
    return view('public.about');
})->name('about');

Route::get('/features', function () {
    return view('public.features');
})->name('features');

Route::get('/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
})->name('credits-and-services');

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

// Language-specific routes
Route::get('/de', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/de/about', function () {
    return view('public.about');
});

Route::get('/de/features', function () {
    return view('public.features');
});

Route::get('/de/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/de/contact', function () {
    return view('contact');
});

Route::get('/de/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/de/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/de/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});

// French routes
Route::get('/fr', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/fr/about', function () {
    return view('public.about');
});

Route::get('/fr/features', function () {
    return view('public.features');
});

Route::get('/fr/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/fr/contact', function () {
    return view('contact');
});

Route::get('/fr/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/fr/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/fr/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});

// Spanish routes  
Route::get('/es', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/es/about', function () {
    return view('public.about');
});

Route::get('/es/features', function () {
    return view('public.features');
});

Route::get('/es/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/es/contact', function () {
    return view('contact');
});

Route::get('/es/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/es/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/es/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});

// Italian routes  
Route::get('/it', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/it/about', function () {
    return view('public.about');
});

Route::get('/it/features', function () {
    return view('public.features');
});

Route::get('/it/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/it/contact', function () {
    return view('contact');
});

Route::get('/it/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/it/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/it/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});

// Portuguese routes  
Route::get('/pt', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/pt/about', function () {
    return view('public.about');
});

Route::get('/pt/features', function () {
    return view('public.features');
});

Route::get('/pt/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/pt/contact', function () {
    return view('contact');
});

Route::get('/pt/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/pt/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/pt/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});

// Hindi routes  
Route::get('/hi', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/hi/about', function () {
    return view('public.about');
});

Route::get('/hi/features', function () {
    return view('public.features');
});

Route::get('/hi/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/hi/contact', function () {
    return view('contact');
});

Route::get('/hi/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/hi/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/hi/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});

// Thai routes  
Route::get('/th', function () {
    $latestBlogs = Blog::published()
        ->orderBy('published_at', 'desc')
        ->take(3)
        ->get();
    return view('welcome', compact('latestBlogs'));
});

Route::get('/th/about', function () {
    return view('public.about');
});

Route::get('/th/features', function () {
    return view('public.features');
});

Route::get('/th/credits-and-services', function () {
    $creditPackages = \App\Models\CreditPackage::where('is_active', true)->orderBy('sort_order')->get();
    return view('public.credits-and-services', compact('creditPackages'));
});

Route::get('/th/contact', function () {
    return view('contact');
});

Route::get('/th/terms', function () {
    $terms = \App\Models\TermsAndConditions::getTerms();
    return view('public.terms', compact('terms'));
});

Route::get('/th/privacy', function () {
    $privacy = \App\Models\TermsAndConditions::getPrivacyPolicy();
    return view('public.privacy', compact('privacy'));
});

Route::get('/th/refund-policy', function () {
    $refund = \App\Models\TermsAndConditions::getRefundPolicy();
    return view('public.refund-policy', compact('refund'));
});



// Industry Demo Routes - Debug Route
Route::get('/demo-test', function() {
    return response()->json(['message' => 'Demo route works', 'layout' => 'public', 'time' => now()]);
})->name('demo-test');

Route::get('/demo/{industry?}', \App\Livewire\Public\IndustryDemo::class)->name('demo');

// Affiliate Registration Route
Route::get('/affiliate/register', \App\Livewire\AffiliateRegistration::class)->name('affiliate.register');

// Affiliate Link Redirect Route
Route::get('/ref/{code}', [\App\Http\Controllers\AffiliateController::class, 'redirect'])->name('affiliate.redirect');

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

// Analytics tracking routes (with CORS and preflight)
Route::options('/analytics/track', function () { return response('', 204); })
    ->middleware([\App\Http\Middleware\CorsMiddleware::class]);
Route::post('/analytics/track', [AnalyticsController::class, 'track'])
    ->middleware([\App\Http\Middleware\CorsMiddleware::class])
    ->name('analytics.track');
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

    // Admin can impersonate a user
    Route::get('/impersonate/{userId}', [\App\Http\Controllers\Admin\ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::get('/impersonate/stop', [\App\Http\Controllers\Admin\ImpersonationController::class, 'stop'])->name('impersonate.stop');
    
    Route::get('/credit-manager', \App\Livewire\Admin\CreditManager::class)->name('credit-manager');
    
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
    
    // Pricing Management Routes
    Route::prefix('pricing')->name('pricing.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PricingController::class, 'index'])->name('index');
        Route::get('/subscription-plans/{id}/edit', [\App\Http\Controllers\Admin\PricingController::class, 'editSubscriptionPlan'])->name('subscription-plans.edit');
        Route::put('/subscription-plans/{id}', [\App\Http\Controllers\Admin\PricingController::class, 'updateSubscriptionPlan'])->name('subscription-plans.update');
        Route::get('/credit-packages/{id}/edit', [\App\Http\Controllers\Admin\PricingController::class, 'editCreditPackage'])->name('credit-packages.edit');
        Route::put('/credit-packages/{id}', [\App\Http\Controllers\Admin\PricingController::class, 'updateCreditPackage'])->name('credit-packages.update');
        Route::get('/credit-packages/create', [\App\Http\Controllers\Admin\PricingController::class, 'createCreditPackage'])->name('credit-packages.create');
        Route::post('/credit-packages', [\App\Http\Controllers\Admin\PricingController::class, 'storeCreditPackage'])->name('credit-packages.store');
    });
    Route::get('/analytics', \App\Livewire\Admin\AnalyticsDashboard::class)->name('analytics');
    Route::get('/reviews', function () {
        return view('admin.reviews');
    })->name('reviews');

    // Quick OTP log viewer (admin only)
    Route::get('/otp-logs', function () {
        $otps = \App\Models\EmailOtp::orderBy('created_at', 'desc')->limit(50)->get();
        return view('admin.otp-logs', compact('otps'));
    })->name('otp-logs');
    
    // Email Management Routes
    Route::get('/email-templates', \App\Livewire\Admin\EmailTemplateManager::class)->name('email-templates');
    Route::get('/email-campaigns', \App\Livewire\Admin\EmailCampaignManager::class)->name('email-campaigns');
    Route::get('/whatsapp-campaigns', \App\Livewire\Admin\WhatsappCampaignManager::class)->name('whatsapp-campaigns');
    Route::get('/email-composer', \App\Livewire\Admin\EmailComposer::class)->name('email-composer');
    
    // Widget Management Route
    Route::get('/widget-script-manager', \App\Livewire\Admin\WidgetScriptManager::class)->name('widget-script-manager');
    
    // Demo Management Route
    Route::get('/demo-manager', \App\Livewire\Admin\DemoManager::class)->name('demo-manager');
    
    // Action Manager Route - Live Data Actions
    Route::get('/action-manager', \App\Livewire\Admin\ActionManager::class)->name('action-manager');
    
    // Token Usage Analytics Route
    Route::get('/token-usage-analytics', \App\Livewire\Admin\TokenUsageAnalytics::class)->name('token-usage-analytics');
    
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
    
    // Subscription route (available without organization for payment)
    Route::get('/subscription', function () {
        return view('customer.subscription');
    })->name('subscription');
    
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
        Route::get('/action-manager', \App\Livewire\Customer\ActionManager::class)->name('action-manager');
        Route::get('/chat-history', \App\Livewire\Customer\ChatHistory::class)->name('chat-history');
        Route::get('/token-usage', \App\Livewire\Customer\TokenUsage::class)->name('token-usage');
        Route::get('/credits', \App\Livewire\Customer\Credits::class)->name('credits');
        Route::get('/leads', \App\Livewire\Customer\LeadsManager::class)->name('leads');
        Route::get('/content', function () {
            return view('customer.content');
        })->name('content');
        Route::get('/analytics', function () {
            return view('customer.analytics');
        })->name('analytics');
        Route::get('/settings', function () {
            return view('customer.settings');
        })->name('settings');

        Route::get('/google-sheets', function () {
            return view('customer.google-sheets');
        })->name('google-sheets');
        Route::get('/widget', function () {
            return view('customer.widget');
        })->name('widget');
        Route::post('/widget/settings', [\App\Http\Controllers\Customer\WidgetSettingsController::class, 'save'])->name('widget.settings.save');
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

// Affiliate Routes (for affiliates to manage their links, commissions, etc.)
Route::middleware(['auth', 'affiliate'])->prefix('affiliate')->name('affiliate.')->group(function () {
    Route::get('/dashboard', \App\Livewire\AffiliateDashboard::class)->name('dashboard');
    Route::get('/links', \App\Livewire\AffiliateLinks::class)->name('links');
    Route::get('/commissions', \App\Livewire\AffiliateCommissions::class)->name('commissions');
    Route::get('/reports', \App\Livewire\AffiliateReports::class)->name('reports');
    Route::get('/profile', \App\Livewire\AffiliateProfile::class)->name('profile');
});

// Widget Routes (Public - no auth required)
Route::prefix('widget')->middleware([\App\Http\Middleware\CorsMiddleware::class, 'noindex'])->group(function () {
    Route::get('{orgId}/script.js', [\App\Http\Controllers\WidgetController::class, 'getWidgetScript'])->name('widget.script');
    Route::get('{orgId}/styles.css', [\App\Http\Controllers\WidgetController::class, 'getWidgetCSS'])->name('widget.styles');
    Route::post('{orgId}/chat', [\App\Http\Controllers\WidgetController::class, 'chat'])->name('widget.chat');
    Route::options('{orgId}/chat', function() { return response('', 204); });
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
    Route::post('create-credit-payment', [\App\Http\Controllers\PayPalController::class, 'createCreditPayment'])
        ->middleware('auth')
        ->name('create-credit-payment');
    Route::get('create-subscription-direct/{planId}/{cycle}', function ($planId, $cycle = 'monthly') {
        return view('payment.paypal-redirect', compact('planId', 'cycle'));
    })->middleware('auth')->name('create-subscription-direct');
    Route::get('success', [\App\Http\Controllers\PayPalController::class, 'handleSuccess'])->name('success');
    Route::get('credit-success', [\App\Http\Controllers\PayPalController::class, 'handleCreditSuccess'])->name('credit-success');
    Route::get('cancel', [\App\Http\Controllers\PayPalController::class, 'handleCancel'])->name('cancel');
    Route::post('webhook', [\App\Http\Controllers\PayPalController::class, 'handleWebhook'])->name('webhook');
});

// Razorpay Routes
Route::prefix('razorpay')->name('razorpay.')->group(function () {
    Route::post('create-subscription', [\App\Http\Controllers\RazorpayController::class, 'createSubscription'])
        ->middleware('auth')
        ->name('create-subscription');
    Route::post('create-onetime-payment', [\App\Http\Controllers\RazorpayController::class, 'createOnetimePayment'])
        ->middleware('auth')
        ->name('create-onetime-payment');
    Route::post('create-credit-payment', [\App\Http\Controllers\RazorpayController::class, 'createCreditPayment'])
        ->middleware('auth')
        ->name('create-credit-payment');
    Route::get('create-subscription-direct/{planId}/{cycle}', function ($planId, $cycle = 'monthly') {
        return view('payment.razorpay-redirect', compact('planId', 'cycle'));
    })->middleware('auth')->name('create-subscription-direct');
    Route::get('create-onetime-direct/{planId}/{cycle}', function ($planId, $cycle = 'monthly') {
        return view('payment.razorpay-onetime-redirect', compact('planId', 'cycle'));
    })->middleware('auth')->name('create-onetime-direct');
    Route::post('success', [\App\Http\Controllers\RazorpayController::class, 'handleSuccess'])->name('success');
    Route::post('onetime-success', [\App\Http\Controllers\RazorpayController::class, 'handleOnetimeSuccess'])->name('onetime-success');
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
    // Log the request for debugging
    \Log::info('OTP Request received', [
        'email' => $request->email,
        'headers' => $request->headers->all(),
        'device_fingerprint' => $request->header('X-Device-Fingerprint')
    ]);

    $request->validate([
        'email' => 'required|email|exists:users,email'
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();
    if (!$user) {
        \Log::error('User not found for OTP', ['email' => $request->email]);
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
            \Log::info('Device is trusted, skipping OTP', ['email' => $request->email, 'fingerprint' => $deviceFingerprint]);
            return response()->json([
                'success' => true,
                'trusted_device' => true,
                'message' => 'Device is trusted, no OTP required'
            ]);
        }
    }

    try {
        // Generate and send OTP
        $otpRecord = \App\Models\EmailOtp::generateForEmail($request->email, 'login');
        
        // Send email notification
        $user->notify(new \App\Notifications\OtpLoginNotification($otpRecord->otp));
        
        \Log::info('OTP sent successfully', ['email' => $request->email, 'otp' => $otpRecord->otp]);

        return response()->json([
            'success' => true,
            'trusted_device' => false,
            'message' => 'OTP sent successfully'
        ]);
    } catch (\Exception $e) {
        \Log::error('OTP sending failed', [
            'email' => $request->email,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to send OTP: ' . $e->getMessage()
        ], 500);
    }
})->name('auth.send-otp');

// Simple login test route (temporary for debugging)
Route::post('/auth/simple-login', function(\Illuminate\Http\Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    \Log::info('Simple login attempt', ['email' => $request->email]);

    if (\Auth::attempt($request->only('email', 'password'))) {
        \Log::info('Simple login successful', ['email' => $request->email]);
        $request->session()->regenerate();
        
        $user = auth()->user();
        if ($user->role === 'admin') {
            return response()->json(['success' => true, 'redirect' => route('admin.dashboard')]);
        } elseif ($user->role === 'customer') {
            return response()->json(['success' => true, 'redirect' => route('customer.dashboard')]);
        }
        return response()->json(['success' => true, 'redirect' => '/']);
    } else {
        \Log::warning('Simple login failed', ['email' => $request->email]);
        return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
    }
})->name('auth.simple-login');

// Registration OTP Routes
Route::post('/auth/send-registration-otp', function(\Illuminate\Http\Request $request) {
    $request->validate([
        'email' => 'required|email|unique:users,email'
    ]);

    try {
        // Generate OTP for registration
        $otpRecord = \App\Models\EmailOtp::generateForEmail($request->email, 'registration');
        
        // Send email notification
        \Illuminate\Support\Facades\Notification::route('mail', $request->email)
            ->notify(new \App\Notifications\OtpRegistrationNotification($otpRecord->otp));

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email address'
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Registration OTP failed', [
            'email' => $request->email,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to send verification code. Please try again.'
        ], 500);
    }
})->name('auth.send-registration-otp');

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
        <loc>' . htmlspecialchars(config('app.url') . $page['url']) . '</loc>
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
        <loc>' . htmlspecialchars(config('app.url') . '/blog/' . $blog->slug) . '</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
        <lastmod>' . $blog->updated_at->toISOString() . '</lastmod>';
        
        if ($blog->featured_image) {
            $sitemap .= '
        <image:image>
            <image:loc>' . htmlspecialchars($blog->featured_image) . '</image:loc>
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

// Customer Reviews Routes
Route::prefix('reviews')->name('reviews.')->group(function () {
    // Public routes
    Route::get('/', function () {
        return view('public.reviews');
    })->name('index');
    Route::get('/organization/{organizationId}', \App\Livewire\Public\ReviewsDisplay::class)->name('organization');
    
    // Auth required routes
    Route::middleware('auth')->group(function () {
        Route::get('/submit/{organizationId?}', function ($organizationId = null) {
            return view('public.review-submit');
        })->name('submit');
    });
});

require __DIR__.'/auth.php';
