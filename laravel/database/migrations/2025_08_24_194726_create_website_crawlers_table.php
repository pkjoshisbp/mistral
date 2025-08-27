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
        Schema::create('website_crawlers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Main Website Crawl"
            $table->string('website_url'); // https://example.com
            $table->text('sitemap_url')->nullable(); // https://example.com/sitemap.xml
            $table->json('specific_pages')->nullable(); // Array of specific URLs to crawl
            $table->json('exclude_patterns')->nullable(); // Patterns to exclude
            $table->json('include_patterns')->nullable(); // Patterns to include
            $table->integer('max_depth')->default(3); // How deep to crawl
            $table->integer('max_pages')->default(50); // Max pages to crawl
            $table->string('crawl_frequency')->default('weekly'); // manual, daily, weekly, monthly
            $table->timestamp('last_crawled_at')->nullable();
            $table->json('crawl_stats')->nullable(); // Pages found, processed, errors
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
        Schema::dropIfExists('website_crawlers');
    }
};
