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
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->foreignId('affiliate_visit_id')->constrained('affiliate_visits')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('commissionable_type');
            $table->unsignedBigInteger('commissionable_id');
            $table->index(['commissionable_type', 'commissionable_id'], 'affiliate_commissions_morphs_index');
            
            // Commission details
            $table->enum('commission_type', ['one_time', 'ongoing']);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('order_value', 15, 2);
            $table->decimal('commission_amount', 15, 2);
            
            // Status tracking
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Ongoing commission tracking
            $table->timestamp('commission_start_date')->nullable();
            $table->timestamp('commission_end_date')->nullable(); // 3 years for ongoing
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_period')->nullable(); // 'monthly', 'yearly'
            
            // Payout tracking
            $table->string('payout_batch_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('payout_details')->nullable();
            
            $table->timestamps();
            
            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'approved_at']);
            $table->index('payout_batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
