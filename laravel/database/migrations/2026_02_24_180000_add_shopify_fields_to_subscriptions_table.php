<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('shopify_subscription_gid')->nullable()->after('razorpay_payment_id');
            $table->string('shopify_shop_domain')->nullable()->after('shopify_subscription_gid');
            $table->string('payment_provider')->nullable()->after('shopify_shop_domain');
            $table->index('shopify_subscription_gid');
            $table->index('payment_provider');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['shopify_subscription_gid']);
            $table->dropIndex(['payment_provider']);
            $table->dropColumn(['shopify_subscription_gid', 'shopify_shop_domain', 'payment_provider']);
        });
    }
};
