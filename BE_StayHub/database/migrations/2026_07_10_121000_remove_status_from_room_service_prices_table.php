<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_service_prices') || ! Schema::hasColumn('room_service_prices', 'status')) {
            return;
        }

        Schema::table('room_service_prices', function (Blueprint $table): void {
            $table->dropIndex('room_service_prices_lookup_idx');
            $table->dropColumn('status');
        });

        Schema::table('room_service_prices', function (Blueprint $table): void {
            $table->index(['room_service_id', 'effective_from', 'effective_to'], 'room_service_prices_lookup_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_service_prices') || Schema::hasColumn('room_service_prices', 'status')) {
            return;
        }

        Schema::table('room_service_prices', function (Blueprint $table): void {
            $table->dropIndex('room_service_prices_lookup_idx');
            $table->unsignedTinyInteger('status')->default(1)->after('effective_to');
        });

        Schema::table('room_service_prices', function (Blueprint $table): void {
            $table->index(['room_service_id', 'status', 'effective_from', 'effective_to'], 'room_service_prices_lookup_idx');
        });
    }
};
