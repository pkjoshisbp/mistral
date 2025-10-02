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
use App\Http\Controllers\Api\WhatsappWebhookController;
use App\Http\Controllers\IntegrationController;

Route::post('/leads', [LeadController::class, 'store']);
Route::get('/leads', [LeadController::class, 'index']);

// Analytics tracking endpoints
Route::post('/analytics/track', [AnalyticsTrackingController::class, 'track']);

// Import FAQs for an organization (auth via organization api_token)
Route::post('/organizations/{slug}/faqs/import', [FaqSyncController::class, 'import']);

// WhatsApp webhook endpoints (global/back-compat)
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive']);

// Per-organization WhatsApp webhook endpoints (preferred)
Route::get('/webhooks/whatsapp/{org_slug}', [WhatsappWebhookController::class, 'verifyForOrg']);
Route::post('/webhooks/whatsapp/{org_slug}', [WhatsappWebhookController::class, 'receiveForOrg']);

// Plugin/App Integration endpoints
Route::prefix('integrations')->group(function () {
    Route::post('/register', [IntegrationController::class, 'register']);
    Route::post('/complete', [IntegrationController::class, 'completeRegistration']);
    Route::get('/widget-script/{org_id}', [IntegrationController::class, 'widgetScript']);
    Route::get('/widget-config/{org_id}', [IntegrationController::class, 'getWidgetConfig']);
    Route::post('/widget-config/{org_id}', [IntegrationController::class, 'updateWidgetConfig']);
    
    // Shopify specific routes
    Route::get('/shopify/oauth/callback', [IntegrationController::class, 'shopifyCallback']);
    Route::post('/webhook/shopify', [IntegrationController::class, 'shopifyWebhook']);
});
