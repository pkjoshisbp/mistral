<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('intent')->nullable()->after('location_data');
            $table->float('intent_confidence')->nullable()->after('intent');
            $table->string('priority')->default('normal')->after('intent_confidence');
            $table->string('status')->default('new')->after('priority');
            $table->text('last_message')->nullable()->after('status');
            $table->timestamp('last_intent_at')->nullable()->after('last_message');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'intent',
                'intent_confidence',
                'priority',
                'status',
                'last_message',
                'last_intent_at'
            ]);
        });
    }
};
