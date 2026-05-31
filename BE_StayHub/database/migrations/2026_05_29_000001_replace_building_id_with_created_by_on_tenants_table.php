<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'building_id')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropForeign(['building_id']);
                $table->dropIndex(['building_id', 'status']);
                $table->dropColumn('building_id');
            });
        }

        if (! Schema::hasColumn('tenants', 'created_by')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->foreignId('created_by')
                    ->after('id')
                    ->constrained('admins')
                    ->restrictOnDelete();

                $table->index(['created_by', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'created_by')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropIndex(['created_by', 'status']);
                $table->dropConstrainedForeignId('created_by');
            });
        }

        if (! Schema::hasColumn('tenants', 'building_id')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->foreignId('building_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('buildings')
                    ->nullOnDelete();

                $table->index(['building_id', 'status']);
            });
        }
    }
};
