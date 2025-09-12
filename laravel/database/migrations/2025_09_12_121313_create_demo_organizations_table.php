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
        Schema::create('demo_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('industry'); // healthcare, education, automotive, etc.
            $table->string('name');
            $table->text('description');
            $table->json('features'); // List of features for this industry
            $table->json('sample_questions'); // Sample questions users can try
            $table->json('ai_responses')->nullable(); // Custom AI responses for specific questions
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique('industry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_organizations');
    }
};
