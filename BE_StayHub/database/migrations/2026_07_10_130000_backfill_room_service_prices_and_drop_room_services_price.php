<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_services')) {
            return;
        }

        $hasRoomServicePrices = Schema::hasTable('room_service_prices');
        $hasPriceColumn = Schema::hasColumn('room_services', 'price');

        if ($hasRoomServicePrices && $hasPriceColumn) {
            DB::table('room_services')
                ->join('services', 'services.id', '=', 'room_services.service_id')
                ->where('services.charge_method', '!=', 1)
                ->whereNotIn('services.slug', ['electric', 'water', 'electricity', 'dien', 'nuoc', 'dien-sinh-hoat', 'nuoc-sinh-hoat'])
                ->select('room_services.id', 'room_services.price', 'room_services.created_at')
                ->orderBy('room_services.id')
                ->chunk(500, function ($roomServices): void {
                    foreach ($roomServices as $roomService) {
                        DB::table('room_service_prices')->updateOrInsert(
                            [
                                'room_service_id' => $roomService->id,
                                'contract_id' => null,
                                'effective_from' => '2026-01-01',
                            ],
                            [
                                'price' => $roomService->price,
                                'effective_to' => null,
                                'created_by' => null,
                                'created_at' => $roomService->created_at ?: now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
        }

        if ($hasPriceColumn) {
            Schema::table('room_services', function (Blueprint $table): void {
                $table->dropColumn('price');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_services') || Schema::hasColumn('room_services', 'price')) {
            return;
        }

        Schema::table('room_services', function (Blueprint $table): void {
            $table->decimal('price', 15, 2)->default(0)->after('service_id');
        });

        DB::table('room_services')
            ->leftJoin('room_service_prices', function ($join): void {
                $join->on('room_service_prices.room_service_id', '=', 'room_services.id')
                    ->whereNull('room_service_prices.contract_id')
                    ->whereNull('room_service_prices.effective_to');
            })
            ->select('room_services.id', 'room_service_prices.price')
            ->orderBy('room_services.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('room_services')
                        ->where('id', $row->id)
                        ->update(['price' => $row->price ?? 0]);
                }
            });
    }
};
