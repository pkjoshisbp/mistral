<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('target_duration_seconds')->default(180);
            $table->string('aspect_ratio')->default('16:9');
            $table->string('language', 10)->default('en');
            $table->string('speaker')->nullable();
            $table->json('scenes');
            $table->json('settings')->nullable();
            $table->string('backend_job_id')->nullable()->index();
            $table->json('backend_response')->nullable();
            $table->string('output_video_path')->nullable();
            $table->string('output_video_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_generation_jobs');
    }
};
