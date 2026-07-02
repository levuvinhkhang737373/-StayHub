<?php

namespace App\Helpers;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DepositRefundExpenseHelper
{
    private const CATEGORY_NAME = 'Hoàn cọc hợp đồng';

    private const CATEGORY_DESCRIPTION = 'Hệ thống tự động tạo khi hoàn cọc cho khách thuê.';

    private const TITLE_MAX_LENGTH = 255;

    private static ?int $refundCategoryId = null;

    /**
     * Tạo phiếu chi hoàn cọc tự động.
     */
    public static function createRefundExpense(
        Contract $contract,
        string $amount,
        string $date,
        int $paymentMethod,
        string $reason,
        ?int $createdBy
    ): Expense {
        $amount = DecimalMoney::normalize($amount);
        $reason = self::normalizeReason($reason);

        if (! DecimalMoney::isPositive($amount)) {
            throw new InvalidArgumentException('Số tiền hoàn cọc phải lớn hơn 0.');
        }

        $room = $contract->room;

        if (! $room || $room->building_id === null) {
            $room = $contract->room()->select(['id', 'building_id'])->first();
        }

        return Expense::query()->create([
            'expense_code' => self::makeExpenseCode(),
            'building_id' => $room?->building_id,
            'room_id' => $contract->room_id,
            'expense_category_id' => self::refundCategoryId(),
            'title' => self::makeTitle($contract, $reason),
            'amount' => $amount,
            'expense_date' => $date,
            'receipt_images' => null,
            'payment_method' => $paymentMethod,
            'note' => $reason,
            'status' => Expense::STATUS_RECORDED,
            'created_by' => $createdBy,
        ]);
    }

    private static function refundCategoryId(): int
    {
        if (self::$refundCategoryId !== null) {
            return self::$refundCategoryId;
        }

        $category = ExpenseCategory::query()->firstOrCreate(
            ['name' => self::CATEGORY_NAME],
            [
                'description' => self::CATEGORY_DESCRIPTION,
                'is_active' => ExpenseCategory::ACTIVE,
                'created_by' => null,
            ]
        );

        self::$refundCategoryId = (int) $category->id;

        return self::$refundCategoryId;
    }

    private static function makeExpenseCode(): string
    {
        $prefix = 'EXP-'.now()->format('Y-m').'-';
        $next = Expense::query()
            ->where('expense_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Expense::query()->where('expense_code', $code)->exists());

        return $code;
    }

    private static function makeTitle(Contract $contract, string $reason): string
    {
        return Str::limit("Hoàn cọc {$contract->contract_code} — {$reason}", self::TITLE_MAX_LENGTH, '');
    }

    private static function normalizeReason(string $reason): string
    {
        $reason = trim($reason);

        return $reason !== '' ? $reason : self::CATEGORY_NAME;
    }
}
