<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('room_types', 'building_id')) {
                $table->foreignId('building_id')->nullable()->after('slug')->constrained('buildings')->nullOnDelete();
                $table->index(['building_id', 'status'], 'room_types_building_status_index');
            }

            $table->dropUnique('room_types_name_unique');
            $table->dropUnique('room_types_slug_unique');
            $table->unique(['building_id', 'name'], 'room_types_building_name_unique');
            $table->unique(['building_id', 'slug'], 'room_types_building_slug_unique');
        });

        DB::statement('UPDATE room_types rt SET building_id = (SELECT r.building_id FROM rooms r WHERE r.room_type_id = rt.id ORDER BY r.id LIMIT 1) WHERE rt.building_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            if (Schema::hasColumn('room_types', 'building_id')) {
                $table->dropUnique('room_types_building_name_unique');
                $table->dropUnique('room_types_building_slug_unique');
                $table->unique('name', 'room_types_name_unique');
                $table->unique('slug', 'room_types_slug_unique');
                $table->dropIndex('room_types_building_status_index');
                $table->dropConstrainedForeignId('building_id');
            }
        });
    }
};
