<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_assistant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preferred_language', 16)->default('en');
            $table->string('tts_provider', 32)->default('xtts');
            $table->json('custom_vocabulary')->nullable();
            $table->json('correction_map')->nullable();
            $table->json('training_samples')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id'], 'pa_profiles_user_org_unique');
            $table->index(['organization_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_assistant_profiles');
    }
};
