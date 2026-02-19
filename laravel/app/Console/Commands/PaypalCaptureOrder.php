<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalCaptureOrder extends Command
{
    protected $signature = 'paypal:capture-order {order_id}';
    protected $description = 'Manually capture a PayPal order and allocate credits idempotently';

    private string $paypalBaseUrl;
    private ?string $clientId;
    private ?string $clientSecret;

    public function __construct()
    {
        parent::__construct();
        $this->paypalBaseUrl = env('PAYPAL_MODE', 'sandbox') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
        $this->clientId = env('PAYPAL_CLIENT_ID');
        $this->clientSecret = env('PAYPAL_CLIENT_SECRET');
    }

    public function handle(): int
    {
        $orderId = (string) $this->argument('order_id');
        if (!$orderId) {
            $this->error('order_id is required');
            return self::FAILURE;
        }

        // Idempotency
        if (\App\Models\CreditTransaction::where('reference_id', $orderId)->exists()) {
            $this->info('Order already processed; no action taken');
            return self::SUCCESS;
        }

        try {
            $accessToken = $this->getAccessToken();

            // Try to capture first
            $captureRes = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId . '/capture');

            $order = null;
            if ($captureRes->successful()) {
                $order = $captureRes->json();
            } else {
                $this->warn('Capture failed; fetching order details');
                Log::warning('Artisan paypal:capture-order capture failed; fetching order', [
                    'order_id' => $orderId,
                    'status' => $captureRes->status(),
                    'body' => $captureRes->body(),
                ]);
                $getRes = Http::withToken($accessToken)
                    ->get($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId);
                if (!$getRes->successful()) {
                    $this->error('Failed to capture or fetch PayPal order');
                    Log::error('Artisan paypal:capture-order unable to fetch order', [
                        'order_id' => $orderId,
                        'status' => $getRes->status(),
                        'body' => $getRes->body(),
                    ]);
                    return self::FAILURE;
                }
                $order = $getRes->json();
            }

            $status = $order['status'] ?? null;
            $this->info('Order status: ' . ($status ?? 'UNKNOWN'));
            if ($status !== 'COMPLETED') {
                $this->error('Order not completed');
                return self::FAILURE;
            }

            $purchaseUnit = $order['purchase_units'][0] ?? [];
            $customId = $purchaseUnit['custom_id'] ?? '';

            $creditPackage = null; $paygPlan = null; $targetUser = null; $tokensToAdd = 0;

            if ($customId && preg_match('/user_(\d+)_credit_(\d+)/', $customId, $m)) {
                $targetUser = \App\Models\User::find((int)$m[1]);
                $creditPackage = \App\Models\PricingPlan::credits()->find((int)$m[2]);
                if ($creditPackage) { $tokensToAdd = (int)$creditPackage->credits; }
            } elseif ($customId && preg_match('/user_(\d+)_payg_(\d+)/', $customId, $m2)) {
                $targetUser = \App\Models\User::find((int)$m2[1]);
                $paygPlan = \App\Models\PricingPlan::subscriptions()->find((int)$m2[2]);
                if ($paygPlan) { $tokensToAdd = (int)($paygPlan->token_cap ?: 100000); }
            }

            if (!$targetUser || $tokensToAdd <= 0) {
                $this->error('Unable to determine user or credits from custom_id: ' . $customId);
                Log::error('Artisan paypal:capture-order unable to determine credit allocation', [
                    'order_id' => $orderId,
                    'custom_id' => $customId,
                ]);
                return self::FAILURE;
            }

            $uc = \App\Models\UserCredit::getOrCreateForUser($targetUser->id);
            $uc->addCredits($tokensToAdd, 'Manual capture credit allocation (PayPal)', [
                'credit_package_id' => $creditPackage->id ?? null,
                'credits' => $tokensToAdd,
                'payment_method' => 'paypal',
                'reference_id' => $orderId,
                'notes' => $creditPackage
                    ? ('Package: ' . ($creditPackage->name ?? '') . ' | USD ' . number_format((float)($creditPackage->price ?? 0), 2))
                    : ($paygPlan
                        ? ('PAYG Plan: ' . ($paygPlan->name ?? '') . ' | USD ' . number_format((float)($paygPlan->price ?? 0), 2))
                        : 'Manual allocation'),
            ]);

            $this->info('Credits allocated: ' . $tokensToAdd . ' to user ID ' . $targetUser->id);
            Log::info('Artisan paypal:capture-order credits allocated', [
                'order_id' => $orderId,
                'user_id' => $targetUser->id,
                'tokens' => $tokensToAdd,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Artisan paypal:capture-order error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Internal error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth((string)$this->clientId, (string)$this->clientSecret)
            ->asForm()
            ->post($this->paypalBaseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            return (string)($response->json()['access_token'] ?? '');
        }

        throw new \RuntimeException('Failed to get PayPal access token');
    }
}
