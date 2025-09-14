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
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['credit', 'debit']); // credit = added, debit = used
            $table->decimal('amount', 15, 4); // Transaction amount
            $table->decimal('balance_after', 15, 4); // Balance after transaction
            $table->string('description')->nullable(); // Transaction description
            $table->json('metadata')->nullable(); // Additional data (package_id, etc.)
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
