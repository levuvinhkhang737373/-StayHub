<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_service_prices')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('room_service_prices'))->pluck('name');

        Schema::table('room_service_prices', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('room_service_prices_room_service_id_effective_from_unique')) {
                $table->dropUnique('room_service_prices_room_service_id_effective_from_unique');
            }

            if (! $indexes->contains('room_service_prices_scope_unique')) {
                $table->unique(['room_service_id', 'contract_id', 'effective_from'], 'room_service_prices_scope_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_service_prices')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('room_service_prices'))->pluck('name');

        Schema::table('room_service_prices', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('room_service_prices_scope_unique')) {
                $table->dropUnique('room_service_prices_scope_unique');
            }

            if (! $indexes->contains('room_service_prices_room_service_id_effective_from_unique')) {
                $table->unique(['room_service_id', 'effective_from'], 'room_service_prices_room_service_id_effective_from_unique');
            }
        });
    }
};
