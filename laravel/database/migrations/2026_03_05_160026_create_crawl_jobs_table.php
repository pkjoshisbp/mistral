<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('crawler_id')->constrained('website_crawlers')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, running, completed, failed, paused
            $table->integer('total_urls')->default(0);
            $table->integer('processed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('batch_size')->default(20);
            $table->integer('current_offset')->default(0);  // next URL index to process
            $table->text('current_url')->nullable();
            $table->json('all_urls')->nullable();       // full list of discovered URLs
            $table->json('failed_urls')->nullable();    // URLs that failed
            $table->json('crawl_log')->nullable();      // last N status messages
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_jobs');
    }
};
