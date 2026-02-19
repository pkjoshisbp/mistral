<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_type'); // subscription | credit
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('billing_period')->nullable(); // monthly | yearly | one_time
            $table->bigInteger('token_cap')->nullable();
            $table->decimal('overage_price_per_100k', 8, 2)->nullable();
            $table->bigInteger('credits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $now = now();

        // Copy subscription plans (monthly + yearly as separate rows)
        $subscriptions = DB::table('subscription_plans')->get();
        foreach ($subscriptions as $plan) {
            $base = [
                'plan_type' => 'subscription',
                'name' => $plan->name,
                'description' => $plan->description,
                'token_cap' => $plan->token_cap_monthly,
                'overage_price_per_100k' => $plan->overage_price_per_100k,
                'is_active' => (bool) $plan->is_active,
                'sort_order' => $plan->sort_order,
                'metadata' => json_encode([
                    'source_table' => 'subscription_plans',
                    'source_id' => $plan->id,
                    'paypal_plan_id' => $plan->paypal_plan_id,
                    'original_slug' => $plan->slug,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($plan->monthly_price !== null) {
                DB::table('pricing_plans')->insert(array_merge($base, [
                    'slug' => $plan->slug . '-monthly',
                    'price' => $plan->monthly_price,
                    'billing_period' => 'monthly',
                ]));
            }

            if ($plan->yearly_price !== null) {
                DB::table('pricing_plans')->insert(array_merge($base, [
                    'slug' => $plan->slug . '-yearly',
                    'price' => $plan->yearly_price,
                    'billing_period' => 'yearly',
                ]));
            }
        }

        // Copy credit packages
        $credits = DB::table('credit_packages')->get();
        foreach ($credits as $pkg) {
            DB::table('pricing_plans')->insert([
                'plan_type' => 'credit',
                'name' => $pkg->name,
                'slug' => $pkg->slug,
                'description' => $pkg->description,
                'price' => $pkg->usd_price,
                'currency' => 'USD',
                'billing_period' => 'one_time',
                'credits' => $pkg->tokens,
                'is_active' => (bool) $pkg->is_active,
                'sort_order' => $pkg->sort_order,
                'metadata' => json_encode([
                    'source_table' => 'credit_packages',
                    'source_id' => $pkg->id,
                    'inr_price' => $pkg->inr_price,
                    'original_slug' => $pkg->slug,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
    }
};
