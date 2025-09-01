<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;

// Public Routes (no authentication required)
Route::get('/', function () {
    return view('welcome');
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

// Alias route for customer namespacing of profile
Route::middleware('auth')->get('/customer/profile', [ProfileController::class, 'edit'])->name('customer.profile');

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
    
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('settings');
    Route::get('/chat-history', \App\Livewire\Admin\ChatHistoryManager::class)->name('chat-history');
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
    Route::get('/data-entry', \App\Livewire\Customer\DataEntry::class)->name('data-entry');
        Route::get('/documents', \App\Livewire\Customer\Documents::class)->name('documents');
        Route::get('/website-crawler', \App\Livewire\Customer\WebsiteCrawler::class)->name('crawler');
        Route::get('/api-integration', \App\Livewire\Customer\ApiIntegration::class)->name('api-integration');
        Route::get('/chat-history', \App\Livewire\Customer\ChatHistory::class)->name('chat-history');
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

// API Routes
Route::prefix('api')->group(function () {
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
});

require __DIR__.'/auth.php';
