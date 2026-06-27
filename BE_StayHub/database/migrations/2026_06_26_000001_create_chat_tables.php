<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('manager_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('tenant_unread_count')->default(0);
            $table->unsignedInteger('admin_unread_count')->default(0);
            $table->timestamp('tenant_last_read_at')->nullable();
            $table->timestamp('admin_last_read_at')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'building_id'], 'chat_conversations_tenant_building_unique');
            $table->index(['manager_admin_id', 'status', 'last_message_at'], 'chat_conversations_manager_inbox_index');
            $table->index(['building_id', 'status', 'last_message_at'], 'chat_conversations_building_inbox_index');
            $table->index(['tenant_id', 'status'], 'chat_conversations_tenant_index');
            $table->index('last_message_id', 'chat_conversations_last_message_index');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('sender_type', 32);
            $table->unsignedBigInteger('sender_id');
            $table->unsignedTinyInteger('sender_role');
            $table->text('body');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at', 'id'], 'chat_messages_conversation_time_index');
            $table->index(['sender_type', 'sender_id'], 'chat_messages_sender_index');
            $table->index(['conversation_id', 'sender_role', 'read_at'], 'chat_messages_read_state_index');
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreign('last_message_id', 'chat_conversations_last_message_foreign')
                ->references('id')
                ->on('chat_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign('chat_conversations_last_message_foreign');
        });

        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
