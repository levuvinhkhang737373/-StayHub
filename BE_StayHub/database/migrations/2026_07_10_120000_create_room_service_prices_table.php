<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_service_id')->constrained('room_services')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['room_service_id', 'effective_from']);
            $table->index(['room_service_id', 'status', 'effective_from', 'effective_to'], 'room_service_prices_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_service_prices');
    }
};
