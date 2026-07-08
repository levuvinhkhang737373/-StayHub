<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fire_safety_alerts');
        Schema::dropIfExists('security_cameras');
    }

    public function down(): void
    {
        // Tính năng AI camera báo cháy/khói/hút thuốc đã được gỡ khỏi hệ thống.
    }
};
