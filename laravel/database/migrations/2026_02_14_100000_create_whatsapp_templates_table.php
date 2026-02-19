<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('language')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->nullable();
            $table->string('header_type')->nullable();
            $table->text('header_text')->nullable();
            $table->text('header_media_url')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->unsignedSmallInteger('body_variable_count')->default(0);
            $table->json('buttons')->nullable();
            $table->json('raw_components')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('waba_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
