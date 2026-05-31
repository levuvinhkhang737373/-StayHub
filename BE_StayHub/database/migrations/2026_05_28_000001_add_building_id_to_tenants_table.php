<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->after('id')
                ->constrained('admins')
                ->restrictOnDelete();

            $table->index(['created_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['created_by', 'status']);
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
