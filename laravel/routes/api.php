<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\FaqSyncController;

Route::post('/leads', [LeadController::class, 'store']);
Route::get('/leads', [LeadController::class, 'index']);

// FAQ Sync Routes (protected by API middleware)
Route::prefix('faq')->group(function () {
    Route::post('/sync', [FaqSyncController::class, 'syncFaqs']);
    Route::post('/import-csv', [FaqSyncController::class, 'importFromCsv']);
    Route::get('/stats/{organizationId}', [FaqSyncController::class, 'getFaqStats']);
    
    // Auto-sync endpoints
    Route::post('/store', [FaqSyncController::class, 'storeSingleFaq']);
    Route::delete('/delete', [FaqSyncController::class, 'deleteSingleFaq']);
    Route::post('/manual-sync', [FaqSyncController::class, 'manualSync']);
});
