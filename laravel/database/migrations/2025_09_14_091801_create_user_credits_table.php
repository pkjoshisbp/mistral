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
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('balance', 15, 4)->default(0); // Credit balance (can be fractional)
            $table->decimal('total_purchased', 15, 4)->default(0); // Total credits ever purchased
            $table->decimal('total_used', 15, 4)->default(0); // Total credits ever used
            $table->timestamp('last_updated_at')->nullable(); // When balance was last updated
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['user_id', 'balance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_credits');
    }
};
