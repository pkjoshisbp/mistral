<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyBillingService
{
    private string $apiVersion = '2025-01';

    public function createAppSubscription(Integration $integration, PricingPlan $plan, string $returnUrl): array
    {
        $price = (float) ($plan->price ?? 0);
        if ($price <= 0) {
            return [
                'ok' => false,
                'message' => 'Invalid plan price for Shopify billing.',
            ];
        }

        $interval = $plan->billing_period === 'yearly' ? 'ANNUAL' : 'EVERY_30_DAYS';
        $currencyCode = strtoupper($plan->currency ?: 'USD');
        $trialDays = max(0, (int) ($plan->metadata['shopify_trial_days'] ?? 0));
        $testMode = (bool) env('SHOPIFY_BILLING_TEST_MODE', false);

        $payload = [
            'query' => <<<'GRAPHQL'
mutation AppSubscriptionCreate(
  $name: String!
  $returnUrl: URL!
  $lineItems: [AppSubscriptionLineItemInput!]!
  $test: Boolean
  $trialDays: Int
  $replacementBehavior: AppSubscriptionReplacementBehavior
) {
  appSubscriptionCreate(
    name: $name
    returnUrl: $returnUrl
    lineItems: $lineItems
    test: $test
    trialDays: $trialDays
    replacementBehavior: $replacementBehavior
  ) {
    appSubscription {
      id
      name
      status
      lineItems {
        plan {
          pricingDetails {
            __typename
            ... on AppRecurringPricing {
              interval
              price {
                amount
                currencyCode
              }
            }
          }
        }
      }
    }
    confirmationUrl
    userErrors {
      field
      message
    }
  }
}
GRAPHQL,
            'variables' => [
                'name' => $plan->name . ' (' . ucfirst((string) $plan->billing_period) . ')',
                'returnUrl' => $returnUrl,
                'lineItems' => [[
                    'plan' => [
                        'appRecurringPricingDetails' => [
                            'price' => [
                                'amount' => $price,
                                'currencyCode' => $currencyCode,
                            ],
                            'interval' => $interval,
                        ],
                    ],
                ]],
                'test' => $testMode,
                'trialDays' => $trialDays,
                'replacementBehavior' => 'APPLY_IMMEDIATELY',
            ],
        ];

        $response = $this->graphql($integration, $payload);

        if (!$response['ok']) {
            return $response;
        }

        $result = $response['data']['appSubscriptionCreate'] ?? null;
        if (!$result) {
            return [
                'ok' => false,
                'message' => 'Invalid Shopify billing response.',
                'raw' => $response['data'] ?? null,
            ];
        }

        $errors = $result['userErrors'] ?? [];
        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => collect($errors)->pluck('message')->filter()->implode(' '),
                'errors' => $errors,
            ];
        }

        $confirmationUrl = $result['confirmationUrl'] ?? null;
        if (!$confirmationUrl) {
            return [
                'ok' => false,
                'message' => 'Shopify did not return confirmation URL.',
            ];
        }

        return [
            'ok' => true,
            'confirmation_url' => $confirmationUrl,
            'subscription' => $result['appSubscription'] ?? null,
        ];
    }

    public function syncSubscriptionFromShopify(
        Integration $integration,
        User $user,
        PricingPlan $plan,
        ?string $chargeId = null
    ): ?Subscription {
        $shopifySubscription = $this->findShopifySubscription($integration, $chargeId, $plan);
        if (!$shopifySubscription) {
            return null;
        }

        $shopifyStatus = strtoupper((string) ($shopifySubscription['status'] ?? 'PENDING'));
        $localStatus = $shopifyStatus === 'ACTIVE' ? 'active' : 'pending';

        $periodStart = now();
        $periodEnd = $plan->billing_period === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        Subscription::where('user_id', $user->id)
            ->where('organization_id', $integration->organization_id)
            ->where('status', 'active')
            ->where(function ($query) use ($shopifySubscription) {
                $query->whereNull('shopify_subscription_gid')
                    ->orWhere('shopify_subscription_gid', '!=', $shopifySubscription['id'] ?? '');
            })
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

        return Subscription::updateOrCreate(
            [
                'shopify_subscription_gid' => $shopifySubscription['id'] ?? null,
            ],
            [
                'user_id' => $user->id,
                'organization_id' => $integration->organization_id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $plan->billing_period === 'yearly' ? 'yearly' : 'monthly',
                'status' => $localStatus,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'tokens_used_this_period' => 0,
                'overage_charges' => 0,
                'paypal_subscription_id' => null,
                'razorpay_subscription_id' => null,
                'razorpay_payment_id' => null,
                'payment_provider' => 'shopify',
                'shopify_shop_domain' => $integration->shop,
            ]
        );
    }

    public function cancelAppSubscription(Integration $integration, string $subscriptionGid): bool
    {
        $payload = [
            'query' => <<<'GRAPHQL'
mutation AppSubscriptionCancel($id: ID!) {
  appSubscriptionCancel(id: $id) {
    appSubscription {
      id
      status
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL,
            'variables' => ['id' => $subscriptionGid],
        ];

        $response = $this->graphql($integration, $payload);
        if (!$response['ok']) {
            return false;
        }

        $cancel = $response['data']['appSubscriptionCancel'] ?? null;
        if (!$cancel) {
            return false;
        }

        $errors = $cancel['userErrors'] ?? [];
        return empty($errors);
    }

    private function findShopifySubscription(Integration $integration, ?string $chargeId, PricingPlan $plan): ?array
    {
        $payload = [
            'query' => <<<'GRAPHQL'
query CurrentAppInstallation {
  currentAppInstallation {
    activeSubscriptions {
      id
      name
      status
      lineItems {
        plan {
          pricingDetails {
            __typename
            ... on AppRecurringPricing {
              interval
              price {
                amount
                currencyCode
              }
            }
          }
        }
      }
    }
  }
}
GRAPHQL,
            'variables' => (object) [],
        ];

        $response = $this->graphql($integration, $payload);
        if (!$response['ok']) {
            return null;
        }

        $subscriptions = $response['data']['currentAppInstallation']['activeSubscriptions'] ?? [];
        if (empty($subscriptions)) {
            return null;
        }

        if ($chargeId) {
            $matched = collect($subscriptions)->first(function ($subscription) use ($chargeId) {
                $id = (string) ($subscription['id'] ?? '');
                return str_ends_with($id, '/' . $chargeId);
            });

            if ($matched) {
                return $matched;
            }
        }

        $targetInterval = $plan->billing_period === 'yearly' ? 'ANNUAL' : 'EVERY_30_DAYS';
        $targetPrice = round((float) $plan->price, 2);

        return collect($subscriptions)->first(function ($subscription) use ($targetInterval, $targetPrice) {
            $pricing = $subscription['lineItems'][0]['plan']['pricingDetails'] ?? null;
            if (!is_array($pricing) || ($pricing['__typename'] ?? '') !== 'AppRecurringPricing') {
                return false;
            }

            $interval = $pricing['interval'] ?? null;
            $amount = round((float) ($pricing['price']['amount'] ?? 0), 2);

            return $interval === $targetInterval && $amount === $targetPrice;
        }) ?? ($subscriptions[0] ?? null);
    }

    private function graphql(Integration $integration, array $payload): array
    {
        if (!$integration->shop || !$integration->access_token) {
            return [
                'ok' => false,
                'message' => 'Shopify integration is missing credentials.',
            ];
        }

        $url = 'https://' . $integration->shop . '/admin/api/' . $this->apiVersion . '/graphql.json';

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $integration->access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Shopify GraphQL request failed', [
                    'shop' => $integration->shop,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'ok' => false,
                    'message' => 'Shopify API request failed.',
                ];
            }

            $data = $response->json();
            if (!empty($data['errors'])) {
                Log::error('Shopify GraphQL errors', [
                    'shop' => $integration->shop,
                    'errors' => $data['errors'],
                ]);

                return [
                    'ok' => false,
                    'message' => collect($data['errors'])->pluck('message')->filter()->implode(' '),
                    'errors' => $data['errors'],
                ];
            }

            return [
                'ok' => true,
                'data' => $data['data'] ?? [],
            ];
        } catch (\Throwable $exception) {
            Log::error('Shopify GraphQL exception', [
                'shop' => $integration->shop,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Unable to connect to Shopify billing API.',
            ];
        }
    }
}
