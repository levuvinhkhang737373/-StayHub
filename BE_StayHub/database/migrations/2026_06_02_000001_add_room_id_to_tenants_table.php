<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'room_id')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->foreignId('room_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('rooms')
                    ->nullOnDelete();

                $table->index(['room_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'room_id')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropIndex(['room_id', 'status']);
                $table->dropConstrainedForeignId('room_id');
            });
        }
    }
};
