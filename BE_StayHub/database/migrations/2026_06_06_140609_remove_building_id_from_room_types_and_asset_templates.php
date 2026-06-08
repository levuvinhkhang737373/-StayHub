<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateRoomTypes = DB::table('room_types')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateRoomTypes as $duplicate) {
            $types = DB::table('room_types')->where('name', $duplicate->name)->orderBy('id')->get();
            $firstId = $types->first()->id;

            $otherIds = $types->where('id', '!=', $firstId)->pluck('id')->toArray();

            if (!empty($otherIds)) {
                DB::table('rooms')->whereIn('room_type_id', $otherIds)->update(['room_type_id' => $firstId]);
                DB::table('room_types')->whereIn('id', $otherIds)->delete();
            }
        }

        $duplicateAssetTemplates = DB::table('asset_templates')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateAssetTemplates as $duplicate) {
            $templates = DB::table('asset_templates')->where('name', $duplicate->name)->orderBy('id')->get();
            $firstId = $templates->first()->id;

            $otherIds = $templates->where('id', '!=', $firstId)->pluck('id')->toArray();

            if (!empty($otherIds)) {
                DB::table('room_assets')->whereIn('asset_template_id', $otherIds)->update(['asset_template_id' => $firstId]);
                DB::table('asset_templates')->whereIn('id', $otherIds)->delete();
            }
        }

        Schema::table('room_types', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('room_types'))->pluck('name');
            if ($indexes->contains('room_types_building_name_unique')) {
                $table->dropUnique('room_types_building_name_unique');
            }
            if ($indexes->contains('room_types_building_slug_unique')) {
                $table->dropUnique('room_types_building_slug_unique');
            }
            if ($indexes->contains('room_types_building_status_index')) {
                $table->dropIndex('room_types_building_status_index');
            }
            
            $foreignKeys = collect(Schema::getForeignKeys('room_types'));
            $hasForeign = $foreignKeys->contains(fn ($fk) => in_array('building_id', $fk['columns'] ?? []))
                || $foreignKeys->contains('name', 'room_types_building_id_foreign');
            if ($hasForeign) {
                $table->dropForeign(['building_id']);
            }
            
            if (Schema::hasColumn('room_types', 'building_id')) {
                $table->dropColumn('building_id');
            }

            if (!$indexes->contains('room_types_name_unique')) {
                $table->unique('name', 'room_types_name_unique');
            }
            if (Schema::hasColumn('room_types', 'slug') && !$indexes->contains('room_types_slug_unique')) {
                $table->unique('slug', 'room_types_slug_unique');
            }
        });

        Schema::table('asset_templates', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('asset_templates'))->pluck('name');
            if ($indexes->contains('asset_templates_building_id_status_index')) {
                $table->dropIndex('asset_templates_building_id_status_index');
            }
            
            $foreignKeys = collect(Schema::getForeignKeys('asset_templates'));
            $hasForeign = $foreignKeys->contains(fn ($fk) => in_array('building_id', $fk['columns'] ?? []))
                || $foreignKeys->contains('name', 'asset_templates_building_id_foreign');
            if ($hasForeign) {
                $table->dropForeign(['building_id']);
            }
            
            if (Schema::hasColumn('asset_templates', 'building_id')) {
                $table->dropColumn('building_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_templates', function (Blueprint $table) {
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->index(['building_id', 'status'], 'asset_templates_building_id_status_index');
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropUnique('room_types_name_unique');
            if (Schema::hasColumn('room_types', 'slug')) {
                $table->dropUnique('room_types_slug_unique');
            }
            $table->foreignId('building_id')->nullable()->after('slug')->constrained('buildings')->nullOnDelete();
            $table->index(['building_id', 'status'], 'room_types_building_status_index');
            $table->unique(['building_id', 'name'], 'room_types_building_name_unique');
            $table->unique(['building_id', 'slug'], 'room_types_building_slug_unique');
        });
    }
};
