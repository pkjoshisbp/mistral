<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PricingPlan;

class PricingController extends Controller
{
    public function index()
    {
        $subscriptionRows = PricingPlan::active()
            ->subscriptions()
            ->orderBy('sort_order')
            ->orderBy('billing_period')
            ->get();

        $grouped = [];
        foreach ($subscriptionRows as $row) {
            $meta = is_array($row->metadata) ? $row->metadata : [];
            $baseSlug = $meta['original_slug'] ?? $row->slug;
            $key = $baseSlug ?: $row->name;
            if (!isset($grouped[$key])) {
                $tokenCap = (int) ($row->token_cap ?? 0);
                $plan = (object) [
                    'id' => $row->id,
                    'monthly_id' => null,
                    'yearly_id' => null,
                    'name' => $row->name,
                    'slug' => $baseSlug ?: $row->slug,
                    'description' => $row->description,
                    'monthly_price' => null,
                    'yearly_price' => null,
                    'token_cap_monthly' => $tokenCap,
                    'overage_price_per_100k' => $row->overage_price_per_100k,
                    'features' => $meta['features'] ?? [],
                    'is_active' => (bool) $row->is_active,
                    'sort_order' => $row->sort_order,
                    'formatted_token_cap' => $tokenCap >= 1000000
                        ? number_format($tokenCap / 1000000, 0) . 'M'
                        : number_format($tokenCap / 1000, 0) . 'K',
                ];
                $grouped[$key] = $plan;
            }

            if ($row->billing_period === 'monthly') {
                $grouped[$key]->monthly_price = $row->price;
                $grouped[$key]->monthly_id = $row->id;
                $grouped[$key]->id = $row->id;
            }
            if ($row->billing_period === 'yearly') {
                $grouped[$key]->yearly_price = $row->price;
                $grouped[$key]->yearly_id = $row->id;
            }
        }

        $subscriptionPlans = collect(array_values($grouped))->map(function ($plan) {
            $costPerMillionTokens = $plan->token_cap_monthly > 0
                ? ((float) ($plan->monthly_price ?? 0) / ($plan->token_cap_monthly / 1000000))
                : 0;
            $plan->cost_per_million_tokens = $costPerMillionTokens;
            return $plan;
        });

        $creditPackages = PricingPlan::credits()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($row) {
                $meta = is_array($row->metadata) ? $row->metadata : [];
                $row->usd_price = $row->price;
                $row->inr_price = $meta['inr_price'] ?? 0;
                $row->tokens = $row->credits;
                $row->features = $meta['features'] ?? [];
                return $row;
            });

        return view('admin.pricing.index', compact('subscriptionPlans', 'creditPackages'));
    }

    // Subscription Plan Methods
    public function editSubscriptionPlan($id)
    {
        $row = PricingPlan::subscriptions()->findOrFail($id);
        $meta = is_array($row->metadata) ? $row->metadata : [];
        $baseSlug = $meta['original_slug'] ?? $row->slug;

        $monthly = PricingPlan::subscriptions()
            ->where('billing_period', 'monthly')
            ->where(function ($query) use ($baseSlug, $row) {
                $query->where('slug', $baseSlug . '-monthly')
                    ->orWhere('id', $row->id);
            })
            ->first();

        $yearly = PricingPlan::subscriptions()
            ->where('billing_period', 'yearly')
            ->where('slug', $baseSlug . '-yearly')
            ->first();

        $tokenCap = (int) ($monthly->token_cap ?? $yearly->token_cap ?? 0);

        $plan = (object) [
            'id' => $monthly->id ?? $row->id,
            'monthly_id' => $monthly->id ?? null,
            'yearly_id' => $yearly->id ?? null,
            'name' => $row->name,
            'slug' => $baseSlug ?: $row->slug,
            'description' => $row->description,
            'monthly_price' => $monthly->price ?? 0,
            'yearly_price' => $yearly->price ?? 0,
            'token_cap_monthly' => $tokenCap,
            'overage_price_per_100k' => $row->overage_price_per_100k,
            'features' => $meta['features'] ?? [],
            'is_active' => (bool) $row->is_active,
            'sort_order' => $row->sort_order,
        ];

        return view('admin.pricing.edit-subscription-plan', compact('plan'));
    }

    public function updateSubscriptionPlan(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'token_cap_monthly' => 'required|integer|min:0',
            'overage_price_per_100k' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer'
        ]);

        // Handle features as array
        $features = [];
        if ($request->has('features')) {
            $features = array_filter(explode("\n", $request->features));
        }
        $monthlyId = $request->input('monthly_id');
        $yearlyId = $request->input('yearly_id');

        $baseData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'token_cap' => $validated['token_cap_monthly'],
            'overage_price_per_100k' => $validated['overage_price_per_100k'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => $validated['sort_order'],
        ];

        if ($monthlyId) {
            $monthly = PricingPlan::subscriptions()->find($monthlyId);
            if ($monthly) {
                $meta = is_array($monthly->metadata) ? $monthly->metadata : [];
                $meta['features'] = $features;
                $monthly->update(array_merge($baseData, [
                    'price' => $validated['monthly_price'],
                    'billing_period' => 'monthly',
                    'metadata' => $meta,
                ]));
            }
        }

        if ($yearlyId) {
            $yearly = PricingPlan::subscriptions()->find($yearlyId);
            if ($yearly) {
                $meta = is_array($yearly->metadata) ? $yearly->metadata : [];
                $meta['features'] = $features;
                $yearly->update(array_merge($baseData, [
                    'price' => $validated['yearly_price'],
                    'billing_period' => 'yearly',
                    'metadata' => $meta,
                ]));
            }
        }

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Subscription plan updated successfully!');
    }

    // Credit Package Methods
    public function editCreditPackage($id)
    {
        $package = PricingPlan::credits()->findOrFail($id);
        $meta = is_array($package->metadata) ? $package->metadata : [];
        $package->usd_price = $package->price;
        $package->inr_price = $meta['inr_price'] ?? 0;
        $package->tokens = $package->credits;
        $package->features = $meta['features'] ?? [];
        return view('admin.pricing.edit-credit-package', compact('package'));
    }

    public function updateCreditPackage(Request $request, $id)
    {
        $package = PricingPlan::credits()->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'usd_price' => 'required|numeric|min:0',
            'inr_price' => 'required|numeric|min:0',
            'tokens' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer'
        ]);

        // Handle features as array
        $features = [];
        if ($request->has('features')) {
            $features = array_filter(explode("\n", $request->features));
        }
        $meta = is_array($package->metadata) ? $package->metadata : [];
        $meta['inr_price'] = $validated['inr_price'];
        $meta['features'] = $features;

        $package->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['usd_price'],
            'credits' => $validated['tokens'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => $validated['sort_order'],
            'metadata' => $meta,
        ]);

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Credit package updated successfully!');
    }

    public function createCreditPackage()
    {
        return view('admin.pricing.create-credit-package');
    }

    public function storeCreditPackage(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:pricing_plans,slug',
            'description' => 'required|string',
            'usd_price' => 'required|numeric|min:0',
            'inr_price' => 'required|numeric|min:0',
            'tokens' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'required|integer'
        ]);

        // Handle features as array
        $features = [];
        if ($request->has('features')) {
            $features = array_filter(explode("\n", $request->features));
        }
        $meta = [
            'inr_price' => $validated['inr_price'],
            'features' => $features,
        ];

        PricingPlan::create([
            'plan_type' => 'credit',
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
            'price' => $validated['usd_price'],
            'currency' => 'USD',
            'billing_period' => 'one_time',
            'credits' => $validated['tokens'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => $validated['sort_order'],
            'metadata' => $meta,
        ]);

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Credit package created successfully!');
    }
}
