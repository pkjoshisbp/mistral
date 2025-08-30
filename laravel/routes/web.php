<?php

use App\Http\Controllers\ProfileController;
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
    return view('public.contact');
})->name('contact');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

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
    
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('settings');
});

// Customer Routes (for customers to manage their organization data)
Route::middleware(['auth', 'customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', function () {
        return view('customer.dashboard');
    })->name('dashboard');
    
    Route::get('/data-sources', \App\Livewire\Customer\DataSources::class)->name('data-sources');
    Route::get('/content', function () {
        return view('customer.content');
    })->name('content');
    Route::get('/analytics', function () {
        return view('customer.analytics');
    })->name('analytics');
    Route::get('/settings', function () {
        return view('customer.settings');
    })->name('settings');
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

require __DIR__.'/auth.php';
