<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Migration cũ đã được vô hiệu hóa vì field giá mặc định của loại phòng đã bỏ.
    }

    public function down(): void
    {
        // Không khôi phục lại field giá mặc định để tránh lệch schema hiện tại.
    }
};
