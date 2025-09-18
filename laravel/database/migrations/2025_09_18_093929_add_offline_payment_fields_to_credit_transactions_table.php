<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->string('reference_id')->nullable()->after('description');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null')->after('reference_id');
            $table->foreignId('credit_package_id')->nullable()->constrained('credit_packages')->onDelete('set null')->after('subscription_id');
            $table->decimal('credits', 10, 4)->nullable()->after('credit_package_id');
            $table->string('payment_method')->nullable()->after('credits');
            $table->string('razorpay_payment_id')->nullable()->after('payment_method');
            $table->text('notes')->nullable()->after('razorpay_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'reference_id', 
                'subscription_id', 
                'credit_package_id', 
                'credits', 
                'payment_method', 
                'razorpay_payment_id', 
                'notes'
            ]);
        });
    }
};
