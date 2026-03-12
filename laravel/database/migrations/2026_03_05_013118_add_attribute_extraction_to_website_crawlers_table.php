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
        Schema::table('website_crawlers', function (Blueprint $table) {
            // What kind of entity/page is being crawled (product, service, doctor, menu-item, property, article, faq)
            $table->string('page_type')->default('product')->after('description');

            // Comma-separated list of attributes to extract per page
            // e.g. "name, price, artist, medium, size, color, description, availability"
            // The LLM will extract these specific fields from each page
            $table->text('attribute_schema')->nullable()->after('page_type');

            // CSS selectors to strip before extraction (noise removal)
            // e.g. ["nav", "footer", ".related-products", "#cart-sidebar", ".breadcrumb"]
            $table->json('noise_selectors')->nullable()->after('attribute_schema');

            // Optional URL pattern filter - only crawl URLs matching this pattern
            // e.g. /products/, /services/, /doctors/, /menu/
            $table->string('url_filter_pattern')->nullable()->after('noise_selectors');

            // How to extract: 'llm' = ask AI to extract attributes, 'structured' = parse HTML tables/lists
            $table->string('extraction_method')->default('llm')->after('url_filter_pattern');

            // Optional custom prompt prefix for LLM extraction (advanced users)
            $table->text('extraction_prompt_override')->nullable()->after('extraction_method');

            // Qdrant data type to store extracted data as (product, service, faq, info)
            $table->string('qdrant_data_type')->default('product')->after('extraction_prompt_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_crawlers', function (Blueprint $table) {
            $table->dropColumn([
                'page_type',
                'attribute_schema',
                'noise_selectors',
                'url_filter_pattern',
                'extraction_method',
                'extraction_prompt_override',
                'qdrant_data_type',
            ]);
        });
    }
};
