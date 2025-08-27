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
        Schema::create('data_sync_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Config name like "Products Sync", "Services Sync"
            $table->string('type')->default('query'); // 'query', 'table', 'view'
            $table->text('query')->nullable(); // Custom SQL query
            $table->json('table_config')->nullable(); // Table selection and field mapping
            $table->json('field_mapping')->nullable(); // How to map fields to vector data
            $table->string('sync_frequency')->default('manual'); // manual, daily, weekly
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sync_configs');
    }
};
