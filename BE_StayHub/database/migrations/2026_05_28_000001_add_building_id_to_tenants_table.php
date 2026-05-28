<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('building_id')
                ->nullable()
                ->after('id')
                ->constrained('buildings')
                ->nullOnDelete();

            $table->index(['building_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['building_id', 'status']);
            $table->dropConstrainedForeignId('building_id');
        });
    }
};
