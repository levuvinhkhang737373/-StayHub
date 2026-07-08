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
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'negotiation_status',
                'proposed_room_price',
                'proposed_services',
                'proposed_vehicles'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->tinyInteger('negotiation_status')->default(0)->comment('0: Không thương lượng, 1: Đang thương lượng, 2: Đã đồng ý, 3: Đã từ chối')->after('status');
            $table->decimal('proposed_room_price', 15, 2)->nullable()->after('negotiation_status');
            $table->json('proposed_services')->nullable()->after('proposed_room_price');
            $table->json('proposed_vehicles')->nullable()->after('proposed_services');
        });
    }
};
