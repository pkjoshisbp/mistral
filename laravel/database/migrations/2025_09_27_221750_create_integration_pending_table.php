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
        Schema::create('integration_pending', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // woocommerce, wordpress
            $table->string('site'); // site URL
            $table->string('token', 64)->unique(); // registration token
            $table->json('metadata')->nullable(); // extra data
            $table->timestamp('expires_at')->nullable(); // token expiry
            $table->timestamps();
            
            $table->index(['token', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_pending');
    }
};
