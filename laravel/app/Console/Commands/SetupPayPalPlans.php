<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PricingPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SetupPayPalPlans extends Command
{
    protected $signature = 'paypal:setup-plans {--dry-run : Show what would be created without actually creating}';
    protected $description = 'Create PayPal billing plans for subscription plans';

    private $paypalBaseUrl;
    private $paypalClientId;
    private $paypalSecret;

    public function __construct()
    {
        parent::__construct();
        $this->paypalBaseUrl = env('PAYPAL_MODE', 'sandbox') === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        $this->paypalClientId = env('PAYPAL_CLIENT_ID');
        $this->paypalSecret = env('PAYPAL_CLIENT_SECRET');
    }

    public function handle()
    {
        $this->info('Setting up PayPal billing plans...');

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('Failed to get PayPal access token');
            return 1;
        }

        $subscriptionPlans = PricingPlan::subscriptions()
            ->whereNull('metadata->paypal_plan_id')
            ->get();

        if ($subscriptionPlans->isEmpty()) {
            $this->info('All subscription plans already have PayPal plan IDs configured.');
            return 0;
        }

        foreach ($subscriptionPlans as $plan) {
            $this->line("Processing plan: {$plan->name}");

            if (($plan->price ?? 0) <= 0) {
                $this->warn("Skipping plan with non-positive price: {$plan->name}");
                continue;
            }

            $planId = $this->createPayPalPlan($accessToken, $plan, $plan->billing_period ?: 'monthly');
            if ($planId && !$this->option('dry-run')) {
                $meta = is_array($plan->metadata) ? $plan->metadata : [];
                $meta['paypal_plan_id'] = $planId;
                $plan->update(['metadata' => $meta]);
                $this->info("✓ PayPal plan created: {$planId}");
            }
        }

        $this->info('PayPal plans setup completed!');
        return 0;
    }

    private function getAccessToken()
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($this->paypalClientId, $this->paypalSecret)
                ->post($this->paypalBaseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            $this->error('PayPal authentication failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            $this->error('PayPal authentication error: ' . $e->getMessage());
            return null;
        }
    }

    private function createPayPalPlan($accessToken, $subscriptionPlan, $cycle = 'monthly')
    {
        try {
            $price = $subscriptionPlan->price;
            $interval = $cycle === 'yearly' ? 'YEAR' : 'MONTH';
            $planName = $subscriptionPlan->name . ' (' . ucfirst($cycle) . ')';

            $planData = [
                'product_id' => 'AIC-' . strtoupper($subscriptionPlan->slug),
                'name' => $planName,
                'description' => $subscriptionPlan->description . ' - ' . ucfirst($cycle) . ' billing',
                'status' => 'ACTIVE',
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => $interval,
                            'interval_count' => 1
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0, // Infinite
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => number_format($price, 2, '.', ''),
                                'currency_code' => 'USD'
                            ]
                        ]
                    ]
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3
                ],
                'taxes' => [
                    'percentage' => '0',
                    'inclusive' => false
                ]
            ];

            if ($this->option('dry-run')) {
                $this->line("Would create PayPal plan: " . json_encode($planData, JSON_PRETTY_PRINT));
                return 'DRY-RUN-PLAN-ID';
            }

            // First create product if it doesn't exist
            $productId = $this->createOrGetProduct($accessToken, $subscriptionPlan);
            if (!$productId) {
                $this->error("Failed to create/get product for {$subscriptionPlan->name}");
                return null;
            }

            $planData['product_id'] = $productId;

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v1/billing/plans', $planData);

            if ($response->successful()) {
                $plan = $response->json();
                $this->info("Created PayPal plan: {$plan['id']} for {$planName}");
                return $plan['id'];
            }

            $this->error("Failed to create PayPal plan for {$planName}: " . $response->body());
            return null;

        } catch (\Exception $e) {
            $this->error("Error creating PayPal plan for {$subscriptionPlan->name}: " . $e->getMessage());
            return null;
        }
    }

    private function createOrGetProduct($accessToken, $subscriptionPlan)
    {
        $meta = is_array($subscriptionPlan->metadata) ? $subscriptionPlan->metadata : [];
        $baseSlug = $meta['original_slug'] ?? $subscriptionPlan->slug;
        $productId = 'AIC-' . strtoupper($baseSlug);

        // Try to get existing product first
        $response = Http::withToken($accessToken)
            ->get($this->paypalBaseUrl . '/v1/catalogs/products/' . $productId);

        if ($response->successful()) {
            return $productId;
        }

        // Create new product
        $productData = [
            'id' => $productId,
            'name' => $subscriptionPlan->name,
            'description' => $subscriptionPlan->description,
            'type' => 'SERVICE',
            'category' => 'SOFTWARE'
        ];

        $response = Http::withToken($accessToken)
            ->post($this->paypalBaseUrl . '/v1/catalogs/products', $productData);

        if ($response->successful()) {
            $product = $response->json();
            $this->info("Created PayPal product: {$product['id']}");
            return $product['id'];
        }

        $this->error("Failed to create PayPal product: " . $response->body());
        return null;
    }
}