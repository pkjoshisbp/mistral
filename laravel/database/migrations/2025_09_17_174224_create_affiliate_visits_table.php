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
        Schema::create('affiliate_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->foreignId('affiliate_link_id')->nullable()->constrained('affiliate_links')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Visit tracking
            $table->string('visitor_id', 64); // Cookie-based tracking
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('referrer')->nullable();
            $table->string('landing_page');
            
            // Attribution tracking (15 days)
            $table->timestamp('first_visit_at')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // 15 days from first visit
            
            // Conversion tracking
            $table->boolean('converted')->default(false);
            $table->timestamp('converted_at')->nullable();
            $table->string('conversion_type')->nullable(); // 'registration', 'purchase'
            $table->decimal('conversion_value', 15, 2)->nullable();
            
            $table->timestamps();
            
            $table->index(['affiliate_id', 'converted']);
            $table->index(['visitor_id', 'expires_at']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_visits');
    }
};
