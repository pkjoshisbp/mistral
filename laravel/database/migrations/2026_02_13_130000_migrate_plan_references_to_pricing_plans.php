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
        // Drop old foreign keys before remapping ids.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_package_id']);
        });

        // Remap subscriptions to pricing_plans (match original plan + billing cycle)
        $subscriptions = DB::table('subscriptions')->select('id', 'subscription_plan_id', 'billing_cycle')->get();
        foreach ($subscriptions as $sub) {
            $pricingPlanId = DB::table('pricing_plans')
                ->where('plan_type', 'subscription')
                ->where('billing_period', $sub->billing_cycle)
                ->whereRaw("JSON_EXTRACT(metadata, '$.source_table') = 'subscription_plans'")
                ->whereRaw("JSON_EXTRACT(metadata, '$.source_id') = ?", [(int) $sub->subscription_plan_id])
                ->value('id');

            if ($pricingPlanId) {
                DB::table('subscriptions')
                    ->where('id', $sub->id)
                    ->update(['subscription_plan_id' => $pricingPlanId]);
            }
        }

        // Remap credit transactions to pricing_plans (credit)
        $transactions = DB::table('credit_transactions')
            ->whereNotNull('credit_package_id')
            ->select('id', 'credit_package_id')
            ->get();
        foreach ($transactions as $tx) {
            $pricingPlanId = DB::table('pricing_plans')
                ->where('plan_type', 'credit')
                ->whereRaw("JSON_EXTRACT(metadata, '$.source_table') = 'credit_packages'")
                ->whereRaw("JSON_EXTRACT(metadata, '$.source_id') = ?", [(int) $tx->credit_package_id])
                ->value('id');

            if ($pricingPlanId) {
                DB::table('credit_transactions')
                    ->where('id', $tx->id)
                    ->update(['credit_package_id' => $pricingPlanId]);
            }
        }

        // Add new foreign keys to pricing_plans
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('subscription_plan_id')
                ->references('id')
                ->on('pricing_plans')
                ->onDelete('cascade');
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreign('credit_package_id')
                ->references('id')
                ->on('pricing_plans')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_package_id']);
        });

        // Note: reversing ID remapping is not automatic.

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('subscription_plan_id')
                ->references('id')
                ->on('subscription_plans')
                ->onDelete('cascade');
        });

        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreign('credit_package_id')
                ->references('id')
                ->on('credit_packages')
                ->onDelete('set null');
        });
    }
};
