<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/organizations', function () {
    return view('organizations');
})->middleware(['auth', 'verified'])->name('organizations');

Route::get('/data-sync', function () {
    return view('data-sync');
})->middleware(['auth', 'verified'])->name('data-sync');

Route::get('/ai-chat', function () {
    return view('ai-chat');
})->middleware(['auth', 'verified'])->name('ai-chat');

Route::get('/widget-manager', function () {
    return view('widget-manager');
})->middleware(['auth', 'verified'])->name('widget-manager');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
