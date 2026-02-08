<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('active','closed','archived','needs_handoff') DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('active','closed','archived') DEFAULT 'active'");
    }
};
