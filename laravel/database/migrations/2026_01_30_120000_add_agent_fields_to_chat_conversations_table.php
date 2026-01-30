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
        Schema::table('chat_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_conversations', 'agent_status')) {
                $table->string('agent_status')->default('ai_active')->after('status');
            }
            if (!Schema::hasColumn('chat_conversations', 'assigned_agent_id')) {
                $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete()->after('agent_status');
            }
            if (!Schema::hasColumn('chat_conversations', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('assigned_agent_id');
            }
            if (!Schema::hasColumn('chat_conversations', 'agent_assigned_at')) {
                $table->timestamp('agent_assigned_at')->nullable()->after('escalated_at');
            }
            if (!Schema::hasColumn('chat_conversations', 'agent_last_active_at')) {
                $table->timestamp('agent_last_active_at')->nullable()->after('agent_assigned_at');
            }
            if (!Schema::hasColumn('chat_conversations', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('agent_last_active_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('chat_conversations', 'assigned_agent_id')) {
                $table->dropForeign(['assigned_agent_id']);
            }
            $columns = [
                'agent_status',
                'assigned_agent_id',
                'escalated_at',
                'agent_assigned_at',
                'agent_last_active_at',
                'closed_at',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('chat_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
