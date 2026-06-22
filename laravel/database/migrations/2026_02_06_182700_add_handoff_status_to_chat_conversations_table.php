<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('active','closed','archived','needs_handoff') DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('active','closed','archived') DEFAULT 'active'");
        }
    }
};
