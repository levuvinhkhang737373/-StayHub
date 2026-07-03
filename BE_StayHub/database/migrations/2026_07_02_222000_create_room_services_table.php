<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamps();

            $table->unique(['room_id', 'service_id']);
        });

        // Seed room services from building service prices
        $rooms = DB::table('rooms')->get();
        foreach ($rooms as $room) {
            $activePrices = DB::table('service_prices')
                ->where('building_id', $room->building_id)
                ->where('status', 1) // STATUS_ACTIVE
                ->get();
            foreach ($activePrices as $price) {
                DB::table('room_services')->insertOrIgnore([
                    'room_id' => $room->id,
                    'service_id' => $price->service_id,
                    'price' => $price->price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_services');
    }
};
