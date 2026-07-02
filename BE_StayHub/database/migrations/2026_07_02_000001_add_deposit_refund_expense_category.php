<?php

use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const CATEGORY_NAME = 'Hoàn cọc hợp đồng';

    private const CATEGORY_DESCRIPTION = 'Hệ thống tự động tạo khi hoàn cọc cho khách thuê.';

    public function up(): void
    {
        ExpenseCategory::query()->firstOrCreate(
            ['name' => self::CATEGORY_NAME],
            [
                'description' => self::CATEGORY_DESCRIPTION,
                'is_active' => true,
                'created_by' => null,
            ]
        );
    }

    public function down(): void
    {
        ExpenseCategory::query()
            ->where('name', self::CATEGORY_NAME)
            ->where('description', self::CATEGORY_DESCRIPTION)
            ->whereNull('created_by')
            ->whereDoesntHave('expenses')
            ->delete();
    }
};
