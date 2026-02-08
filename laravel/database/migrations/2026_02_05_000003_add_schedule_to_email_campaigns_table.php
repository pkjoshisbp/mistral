<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('sender_name');
        });

        DB::statement("ALTER TABLE email_campaigns MODIFY status ENUM('draft','scheduled','sending','sent','failed') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_campaigns')->where('status', 'scheduled')->update(['status' => 'draft']);

        DB::statement("ALTER TABLE email_campaigns MODIFY status ENUM('draft','sending','sent','failed') DEFAULT 'draft'");

        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
