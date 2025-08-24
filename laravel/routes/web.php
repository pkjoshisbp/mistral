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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
