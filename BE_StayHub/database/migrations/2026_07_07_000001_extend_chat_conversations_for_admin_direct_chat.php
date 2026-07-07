<?php

use App\Models\ChatConversation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_conversations', 'conversation_type')) {
                $table->unsignedTinyInteger('conversation_type')
                    ->default(ChatConversation::TYPE_TENANT_MANAGER)
                    ->after('id');
            }

            if (! Schema::hasColumn('chat_conversations', 'super_admin_id')) {
                $table->foreignId('super_admin_id')
                    ->nullable()
                    ->after('manager_admin_id')
                    ->constrained('admins')
                    ->restrictOnDelete();
            }
        });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (! Schema::hasIndex('chat_conversations', 'chat_conversations_super_manager_unique')) {
                $table->unique(['super_admin_id', 'manager_admin_id'], 'chat_conversations_super_manager_unique');
            }

            if (! Schema::hasIndex('chat_conversations', 'chat_conversations_type_inbox_index')) {
                $table->index(['conversation_type', 'status', 'last_message_at'], 'chat_conversations_type_inbox_index');
            }

            if (! Schema::hasIndex('chat_conversations', 'chat_conversations_super_admin_inbox_index')) {
                $table->index(['super_admin_id', 'status', 'last_message_at'], 'chat_conversations_super_admin_inbox_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (Schema::hasIndex('chat_conversations', 'chat_conversations_super_admin_inbox_index')) {
                $table->dropIndex('chat_conversations_super_admin_inbox_index');
            }

            if (Schema::hasIndex('chat_conversations', 'chat_conversations_type_inbox_index')) {
                $table->dropIndex('chat_conversations_type_inbox_index');
            }

            if (Schema::hasIndex('chat_conversations', 'chat_conversations_super_manager_unique')) {
                $table->dropUnique('chat_conversations_super_manager_unique');
            }
        });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_conversations', 'super_admin_id')) {
                $table->dropConstrainedForeignId('super_admin_id');
            }

            if (Schema::hasColumn('chat_conversations', 'conversation_type')) {
                $table->dropColumn('conversation_type');
            }
        });
    }
};
