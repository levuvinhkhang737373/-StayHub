<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_conversations', 'building_id')) {
                $table->foreignId('building_id')->nullable()->change();
            }

            if (Schema::hasColumn('chat_conversations', 'room_id')) {
                $table->foreignId('room_id')->nullable()->change();
            }

            if (Schema::hasColumn('chat_conversations', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_conversations', 'building_id')) {
                $table->foreignId('building_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('chat_conversations', 'room_id')) {
                $table->foreignId('room_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('chat_conversations', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            }
        });
    }
};
