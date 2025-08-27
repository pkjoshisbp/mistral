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
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['crawler', 'file_upload', 'google_sheets', 'database', 'api_push']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config'); // Store configuration like URLs, credentials, etc.
            $table->enum('status', ['active', 'inactive', 'syncing', 'error'])->default('inactive');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_stats')->nullable(); // Store sync statistics
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
