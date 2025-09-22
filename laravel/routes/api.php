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
use App\Http\Controllers\Api\AnalyticsTrackingController;
use App\Http\Controllers\Api\FaqSyncController;

Route::post('/leads', [LeadController::class, 'store']);
Route::get('/leads', [LeadController::class, 'index']);

// Analytics tracking endpoints
Route::post('/analytics/track', [AnalyticsTrackingController::class, 'track']);

// Import FAQs for an organization (auth via organization api_token)
Route::post('/organizations/{slug}/faqs/import', [FaqSyncController::class, 'import']);
