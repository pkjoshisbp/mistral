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
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->string('link_code', 20)->unique();
            $table->string('type', 20); // 'registration', 'subscription', 'credit_package'
            $table->string('target_url'); // The page being promoted
            $table->string('package_id')->nullable(); // For specific packages
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Statistics
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0.00);
            $table->decimal('earnings', 15, 2)->default(0.00);
            
            $table->timestamps();
            
            $table->index(['affiliate_id', 'type']);
            $table->index('link_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_links');
    }
};
