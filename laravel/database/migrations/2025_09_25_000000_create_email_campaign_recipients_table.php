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
        Schema::create('email_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->onDelete('cascade');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('recipient_email');
            $table->json('variables')->nullable();
            $table->enum('status', ['pending','sent','failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['email_campaign_id','status']);
            $table->index('recipient_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_campaign_recipients');
    }
};