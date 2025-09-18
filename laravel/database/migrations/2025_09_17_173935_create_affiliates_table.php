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
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('affiliate_code', 20)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'rejected'])->default('pending');
            
            // Commission settings
            $table->enum('commission_type', ['one_time', 'ongoing'])->default('one_time');
            $table->decimal('one_time_rate', 5, 2)->default(30.00); // 30% default
            $table->decimal('ongoing_rate', 5, 2)->default(10.00); // 10% default
            
            // Payment details
            $table->string('payment_method', 20)->nullable(); // 'bank', 'upi'
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('upi_id')->nullable();
            
            // Statistics
            $table->integer('total_clicks')->default(0);
            $table->integer('total_registrations')->default(0);
            $table->integer('total_purchases')->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0.00);
            $table->decimal('paid_earnings', 15, 2)->default(0.00);
            $table->decimal('pending_earnings', 15, 2)->default(0.00);
            
            // Metadata
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_payout_at')->nullable();
            $table->json('metadata')->nullable(); // For additional settings
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
