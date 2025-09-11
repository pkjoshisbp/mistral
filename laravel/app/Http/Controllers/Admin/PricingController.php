<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlan;
use App\Models\CreditPackage;

class PricingController extends Controller
{
    public function index()
    {
                // Add subscription plan pricing analysis
        $subscriptionPlans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                $costPerMillionTokens = $plan->token_cap_monthly > 0 
                    ? ($plan->monthly_price / ($plan->token_cap_monthly / 1000000)) 
                    : 0;
                
                $plan->cost_per_million_tokens = $costPerMillionTokens;
                return $plan;
            });
        $creditPackages = CreditPackage::orderBy('sort_order')->get();

        return view('admin.pricing.index', compact('subscriptionPlans', 'creditPackages'));
    }

    // Subscription Plan Methods
    public function editSubscriptionPlan($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        return view('admin.pricing.edit-subscription-plan', compact('plan'));
    }

    public function updateSubscriptionPlan(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
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
        $validated['features'] = $features;

        $plan->update($validated);

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Subscription plan updated successfully!');
    }

    // Credit Package Methods
    public function editCreditPackage($id)
    {
        $package = CreditPackage::findOrFail($id);
        return view('admin.pricing.edit-credit-package', compact('package'));
    }

    public function updateCreditPackage(Request $request, $id)
    {
        $package = CreditPackage::findOrFail($id);
        
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
        $validated['features'] = $features;

        $package->update($validated);

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
            'slug' => 'required|string|unique:credit_packages,slug',
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
        $validated['features'] = $features;

        CreditPackage::create($validated);

        return redirect()->route('admin.pricing.index')
            ->with('success', 'Credit package created successfully!');
    }
}
