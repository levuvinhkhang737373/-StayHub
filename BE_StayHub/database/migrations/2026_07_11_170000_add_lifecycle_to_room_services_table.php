<?php

use App\Models\Contract;
use App\Models\Room;
use App\Models\RoomMovement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_services')) {
            return;
        }

        Schema::table('room_services', function (Blueprint $table): void {
            if (! Schema::hasColumn('room_services', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('service_id');
            }

            if (! Schema::hasColumn('room_services', 'ended_at')) {
                $table->date('ended_at')->nullable()->after('is_active');
            }
        });

        DB::table('room_services')->whereNull('is_active')->update(['is_active' => true]);

        $this->deactivateAlreadyVacatedTransferRooms();

        Schema::table('room_services', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('room_services'))->pluck('name');

            if (! $indexes->contains('room_services_room_active_idx')) {
                $table->index(['room_id', 'is_active'], 'room_services_room_active_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_services')) {
            return;
        }

        Schema::table('room_services', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('room_services'))->pluck('name');

            if ($indexes->contains('room_services_room_active_idx')) {
                $table->dropIndex('room_services_room_active_idx');
            }

            if (Schema::hasColumn('room_services', 'ended_at')) {
                $table->dropColumn('ended_at');
            }

            if (Schema::hasColumn('room_services', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }

    private function deactivateAlreadyVacatedTransferRooms(): void
    {
        if (! Schema::hasTable('room_movements') || ! Schema::hasTable('contracts')) {
            return;
        }

        DB::table('room_movements')
            ->select('from_room_id', DB::raw('MAX(movement_date) as latest_movement_date'))
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->where('status', RoomMovement::STATUS_EXECUTED)
            ->whereNotNull('from_room_id')
            ->groupBy('from_room_id')
            ->orderBy('from_room_id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $roomId = (int) $row->from_room_id;

                    if (! $this->canDeactivateRoomServices($roomId)) {
                        continue;
                    }

                    $endedAt = Carbon::parse($row->latest_movement_date)->subDay()->toDateString();

                    DB::table('room_services')
                        ->where('room_id', $roomId)
                        ->update([
                            'is_active' => false,
                            'ended_at' => $endedAt,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function canDeactivateRoomServices(int $roomId): bool
    {
        $room = DB::table('rooms')->where('id', $roomId)->first(['current_occupants']);
        if (! $room || (int) $room->current_occupants > 0) {
            return false;
        }

        return ! DB::table('contracts')
            ->where('room_id', $roomId)
            ->whereIn('status', Contract::RESERVED_STATUSES)
            ->exists();
    }
};
