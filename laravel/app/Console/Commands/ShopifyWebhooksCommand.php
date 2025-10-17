<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Integration;

class ShopifyWebhooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *  php artisan shopify:webhooks {shop} {action}
     *  - shop: my-store.myshopify.com
     *  - action: list | register
     */
    protected $signature = 'shopify:webhooks {shop : Shopify shop domain (e.g., example.myshopify.com)} {action : list|register}';

    /**
     * The console command description.
     */
    protected $description = 'List or register mandatory Shopify webhooks for a shop';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shop = strtolower(trim($this->argument('shop')));
        $action = strtolower(trim($this->argument('action')));

        if (!str_ends_with($shop, '.myshopify.com')) {
            $shop = rtrim($shop, '/') . '.myshopify.com';
        }

        $integration = Integration::where('provider', 'shopify')->where('shop', $shop)->first();
        if (!$integration || empty($integration->access_token)) {
            $this->error("No Shopify integration with access token found for {$shop}. Please install the app first via /shopify/install");
            return self::FAILURE;
        }

        $token = $integration->access_token;
        $baseUrl = "https://{$shop}/admin/api/2025-01";

        if ($action === 'list') {
            return $this->listWebhooks($baseUrl, $token);
        }

        if ($action === 'register') {
            return $this->registerWebhooks($baseUrl, $token);
        }

        $this->error('Unknown action. Use: list | register');
        return self::FAILURE;
    }

    private function listWebhooks(string $baseUrl, string $token): int
    {
        $resp = Http::withHeaders(['X-Shopify-Access-Token' => $token])->get($baseUrl . '/webhooks.json');
        if (!$resp->ok()) {
            $this->error('Failed to fetch webhooks: ' . $resp->status() . ' ' . $resp->body());
            return self::FAILURE;
        }

        $webhooks = $resp->json('webhooks') ?? [];
        $this->info('Existing webhooks:');
        foreach ($webhooks as $wh) {
            $this->line(sprintf('- id:%s topic:%s address:%s', $wh['id'] ?? 'n/a', $wh['topic'] ?? 'n/a', $wh['address'] ?? ''));
        }
        $this->line(sprintf('Total: %d', count($webhooks)));
        return self::SUCCESS;
    }

    private function registerWebhooks(string $baseUrl, string $token): int
    {
        $address = config('app.url') . '/shopify/webhooks';
        $topics = [
            'app/uninstalled',
            'customers/data_request',
            'customers/redact',
            'shop/redact',
        ];

        // Get existing to avoid duplicates
        $existing = Http::withHeaders(['X-Shopify-Access-Token' => $token])
            ->get($baseUrl . '/webhooks.json')
            ->json('webhooks') ?? [];

        $existingKey = [];
        foreach ($existing as $wh) {
            $existingKey[$wh['topic'] . '|' . $wh['address']] = true;
        }

        $headers = [
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ];

        $created = 0;
        foreach ($topics as $topic) {
            $key = $topic . '|' . $address;
            if (isset($existingKey[$key])) {
                $this->line("Already exists: {$topic} -> {$address}");
                continue;
            }

            $payload = [
                'webhook' => [
                    'topic' => $topic,
                    'address' => $address,
                    'format' => 'json',
                ],
            ];

            $resp = Http::withHeaders($headers)->post($baseUrl . '/webhooks.json', $payload);
            if ($resp->created() || $resp->ok()) {
                $created++;
                $this->info("Registered: {$topic}");
            } else {
                $this->warn('Failed to register ' . $topic . ': ' . $resp->status() . ' ' . $resp->body());
            }
        }

        $this->info("Done. Created: {$created}");
        return self::SUCCESS;
    }
}
