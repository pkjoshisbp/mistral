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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // shopify, woocommerce, wordpress
            $table->string('shop')->nullable(); // shop domain for shopify, site URL for wp
            $table->text('access_token')->nullable(); // encrypted tokens
            $table->json('settings')->nullable(); // widget settings, etc.
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->unique(['organization_id', 'provider']);
            $table->index(['provider', 'shop']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
