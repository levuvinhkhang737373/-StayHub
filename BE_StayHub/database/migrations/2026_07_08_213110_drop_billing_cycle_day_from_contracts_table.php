<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'billing_cycle_day')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('billing_cycle_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('contracts', 'billing_cycle_day')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_cycle_day')->default(5);
        });
    }
};
