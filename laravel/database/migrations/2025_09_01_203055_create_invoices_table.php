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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('billing_period'); // e.g., "January 2025" or "2024-2025"
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('overage_charges', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->string('payment_method')->nullable(); // PayPal, Razorpay, etc.
            $table->string('payment_transaction_id')->nullable();
            $table->json('invoice_data')->nullable(); // Store detailed invoice data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
