# Tự động tạo phiếu chi khi hoàn cọc

## Mô tả vấn đề

Khi hoàn cọc cho khách thuê, hệ thống chỉ tạo `ContractDepositTransaction` type=REFUND (ghi sổ cọc) nhưng **không tạo `Expense`** (phiếu chi). Dẫn đến báo cáo tài chính bị lệch — tiền chi ra thực tế không được ghi nhận, lợi nhuận hiển thị cao hơn thực tế.

## Tất cả các điểm cần bổ sung phiếu chi

Sau khi rà soát toàn bộ codebase, có **4 điểm** mà tiền cọc được hoàn/chi ra:

| # | Điểm phát sinh | File | Hiện tại | Cần bổ sung |
|---|---------------|------|----------|-------------|
| 1 | **Thanh lý hợp đồng** — hoàn cọc cho tenant | `BE_StayHub/app/Http/Controllers/Admin/ContractController.php` (line ~1590-1598) | Tạo deposit tx REFUND | ❌ Thiếu Expense |
| 2 | **Admin thêm giao dịch cọc thủ công** type=REFUND | `BE_StayHub/app/Http/Controllers/Admin/ContractController.php` (line ~902-943) | Tạo deposit tx REFUND | ❌ Thiếu Expense |
| 3 | **Chuyển phòng scheduled** — `manual_refund_amount` (tiền dư cọc trả lại tenant) | `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php` (line ~445-476) | Ghi vào RoomMovement field, KHÔNG tạo deposit tx REFUND | ❌ Thiếu cả deposit tx lẫn Expense |
| 4 | **Gia hạn hợp đồng** — cọc cũ dư so với cọc mới | `BE_StayHub/app/Http/Controllers/Admin/ContractController.php` (line ~833-869) | Chỉ kết chuyển (DEDUCT + TRANSFER_IN), không xử lý trường hợp cọc cũ > cọc mới sau khi chuyển | ⚠️ Chưa có flow hoàn tiền dư |

> **⚠️ QUAN TRỌNG — Điểm #3 là lỗ hổng nghiêm trọng nhất:**
>
> Khi chuyển phòng mà cọc cũ > (khấu trừ + phí + cọc mới), phần dư (`manual_refund_amount`) chỉ ghi vào field trên `RoomMovement` mà **KHÔNG tạo** deposit transaction REFUND trên source contract → sổ cọc hợp đồng cũ vẫn còn dư → sai.

---

## Proposed Changes

### Migration — Tạo danh mục chi phí hệ thống "Hoàn cọc hợp đồng"

#### [NEW] `database/migrations/xxxx_add_deposit_refund_expense_category.php`

Tạo migration để thêm danh mục chi phí hệ thống:

```php
ExpenseCategory::firstOrCreate(
    ['name' => 'Hoàn cọc hợp đồng'],
    ['description' => 'Hệ thống tự động tạo khi hoàn cọc cho khách thuê.', 'is_active' => true, 'created_by' => null]
);
```

> **📝 GHI CHÚ:** Dùng `firstOrCreate` để idempotent — chạy lại migration không bị trùng.

---

### Helper class — Logic tạo phiếu chi dùng chung

#### [NEW] `app/Helpers/DepositRefundExpenseHelper.php`

Tạo helper class (không phải trait) chứa logic tạo Expense khi hoàn cọc, dùng chung cho cả 3-4 điểm:

```php
class DepositRefundExpenseHelper
{
    /**
     * Tạo phiếu chi hoàn cọc tự động.
     *
     * @param Contract $contract   Hợp đồng liên quan
     * @param string   $amount     Số tiền hoàn (decimal string)
     * @param string   $date       Ngày chi (Y-m-d)
     * @param int      $paymentMethod  Phương thức (1=Cash, 2=Bank)
     * @param string   $reason     Lý do (vd: "Hoàn cọc khi thanh lý hợp đồng")
     * @param int|null $createdBy  Admin ID
     */
    public static function createRefundExpense(
        Contract $contract,
        string $amount,
        string $date,
        int $paymentMethod,
        string $reason,
        ?int $createdBy
    ): Expense;
}
```

**Logic bên trong:**

1. Tìm `ExpenseCategory` có name = "Hoàn cọc hợp đồng" (cache kết quả)
2. Generate `expense_code` theo format `EXP-YYYY-MM-XXXX` (copy logic từ `ExpenseController::makeExpenseCode()`)
3. Tạo `Expense` record với:
   - `building_id` = `$contract->room->building_id`
   - `room_id` = `$contract->room_id`
   - `title` = "Hoàn cọc {$contract->contract_code} — {$reason}"
   - `status` = `Expense::STATUS_RECORDED`

> **💡 MẸO:** Tách ra helper thay vì trait vì logic này được gọi từ cả Controller lẫn Console Command — 2 class hoàn toàn khác nhau.

---

### Điểm #1 — Thanh lý hợp đồng

#### [MODIFY] `BE_StayHub/app/Http/Controllers/Admin/ContractController.php`

Trong method `createTerminationDepositTransactions()` (line ~1590), sau khi tạo deposit tx REFUND, thêm:

```php
if ($refundCents > 0) {
    // Deposit transaction REFUND (đã có)
    $contract->depositTransactions()->create([...]);

    // ✅ THÊM: Tạo phiếu chi
    DepositRefundExpenseHelper::createRefundExpense(
        contract: $contract,
        amount: $this->centsToDecimal($refundCents),
        date: $transactionDate,
        paymentMethod: $paymentMethod,
        reason: 'Thanh lý hợp đồng',
        createdBy: $admin->id,
    );
}
```

---

### Điểm #2 — Admin thêm giao dịch cọc thủ công type=REFUND

#### [MODIFY] `BE_StayHub/app/Http/Controllers/Admin/ContractController.php`

Trong method `addDepositTransaction()` (line ~902), sau khi tạo deposit tx, kiểm tra nếu type=REFUND thì tạo phiếu chi:

```php
$newTransaction = DB::transaction(function () use (...) {
    // ... existing logic ...
    $tx = $contractModel->depositTransactions()->create([...]);

    // ✅ THÊM: Nếu là hoàn cọc → tạo phiếu chi
    if ((int) $validated['transaction_type'] === ContractDepositTransaction::TRANSACTION_TYPE_REFUND) {
        DepositRefundExpenseHelper::createRefundExpense(
            contract: $contractModel,
            amount: $this->normalizeDecimal($validated['amount']),
            date: $validated['transaction_date'],
            paymentMethod: (int) $validated['payment_method'],
            reason: $validated['note'] ?? 'Hoàn cọc thủ công',
            createdBy: $admin->id,
        );
    }

    return $tx;
});
```

---

### Điểm #3 — Chuyển phòng scheduled (manual_refund_amount)

#### [MODIFY] `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php`

Trong method `writeDepositSettlement()` (line ~445), thêm xử lý `manual_refund_amount`:

```php
private function writeDepositSettlement(...): void
{
    // ... existing DEDUCT logic ...
    // ... existing TRANSFER_OUT + TRANSFER_IN logic ...

    // ✅ THÊM: Hoàn phần dư cho tenant
    if (DecimalMoney::isPositive($settlement['manual_refund_amount'])) {
        // 1. Ghi sổ cọc — tạo deposit tx REFUND trên source contract
        $sourceContract->depositTransactions()->create([
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            'amount' => $settlement['manual_refund_amount'],
            'transaction_date' => $movementDate->toDateString(),
            'payment_method' => null,
            'note' => "Hoàn cọc dư khi chuyển phòng sang hợp đồng #{$destinationContract->id}.",
            'created_by' => $admin->id,
        ]);

        // 2. Tạo phiếu chi
        DepositRefundExpenseHelper::createRefundExpense(
            contract: $sourceContract,
            amount: $settlement['manual_refund_amount'],
            date: $movementDate->toDateString(),
            paymentMethod: Expense::PAYMENT_METHOD_CASH,
            reason: 'Hoàn cọc dư khi chuyển phòng',
            createdBy: $admin->id,
        );
    }
}
```

> **⚠️ CẢNH BÁO:** Điểm này hiện tại `manual_refund_amount` chỉ là số trên `RoomMovement` mà **không tạo deposit tx REFUND** → sổ cọc hợp đồng cũ bị sai. Cần thêm cả deposit tx lẫn phiếu chi.

---

### Điểm #4 — Gia hạn hợp đồng (cọc cũ dư)

Hiện tại flow gia hạn ở `ContractController.php` (line ~833-869) chỉ xử lý:

- Cọc cũ ≤ cọc mới → kết chuyển toàn bộ + thu thêm phần thiếu
- Cọc cũ > cọc mới → **chỉ kết chuyển bằng mức cọc mới**, phần dư trên hợp đồng cũ không được hoàn

Cần thêm logic: nếu `$oldBalanceCents > $newDepositAmountCents`, hoàn phần dư `$oldBalanceCents - $newDepositAmountCents`:

```php
if ($oldBalanceCents > $newDepositAmountCents) {
    $surplusCents = $oldBalanceCents - $newDepositAmountCents;

    // Kết chuyển phần cần
    // ... (DEDUCT on old, TRANSFER_IN on new - đã có) ...

    // Hoàn phần dư
    $oldContract->depositTransactions()->create([
        'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
        'amount' => $this->centsToDecimal($surplusCents),
        'transaction_date' => now()->toDateString(),
        'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_CASH,
        'note' => "Hoàn cọc dư khi gia hạn hợp đồng sang ID #{$contract->id}.",
        'created_by' => $admin->id,
    ]);

    DepositRefundExpenseHelper::createRefundExpense(
        contract: $oldContract,
        amount: $this->centsToDecimal($surplusCents),
        date: now()->toDateString(),
        paymentMethod: Expense::PAYMENT_METHOD_CASH,
        reason: 'Hoàn cọc dư khi gia hạn hợp đồng',
        createdBy: $admin->id,
    );
}
```

> **🚨 LƯU Ý:** Flow gia hạn hiện tại dùng `DEDUCT` trên hợp đồng cũ thay vì `TRANSFER_OUT`, khác với flow chuyển phòng scheduled. Cần giữ nguyên convention này để tránh break.

---

## Tóm tắt file thay đổi

| File | Hành động | Mức ảnh hưởng |
|------|----------|---------------|
| [NEW] `BE_StayHub/database/migrations/xxxx_add_deposit_refund_expense_category.php` | Thêm danh mục "Hoàn cọc hợp đồng" | Nhẹ |
| [NEW] `BE_StayHub/app/Helpers/DepositRefundExpenseHelper.php` | Helper tạo phiếu chi hoàn cọc | Nhẹ |
| [MODIFY] `BE_StayHub/app/Http/Controllers/Admin/ContractController.php` | Thêm gọi helper ở 3 điểm (#1, #2, #4) | Trung bình (~30 dòng thêm) |
| [MODIFY] `BE_StayHub/app/Console/Commands/ExecuteScheduledRoomTransfers.php` | Thêm deposit tx REFUND + phiếu chi cho manual_refund (#3) | Trung bình (~20 dòng thêm) |

**Không cần sửa FE** — phiếu chi tự động xuất hiện trong danh sách Expense và báo cáo tài chính.

---

## Verification Plan

### Automated Tests

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan tinker
# Kiểm tra ExpenseCategory 'Hoàn cọc hợp đồng' tồn tại
```

### Manual Verification

1. **Thanh lý hợp đồng** có hoàn cọc → kiểm tra bảng `expenses` có record mới
2. **Thêm giao dịch cọc thủ công** type=REFUND → kiểm tra `expenses`
3. **Chuyển phòng** có `manual_refund_amount > 0` → kiểm tra cả `contract_deposit_transactions` (REFUND) lẫn `expenses`
4. **Báo cáo tài chính** → khoản hoàn cọc xuất hiện trong chi phí, lợi nhuận tính đúng

---

## Open Questions

> **❓ Q1 — Điểm #4 (Gia hạn hợp đồng cọc dư):**
>
> Hiện tại flow chỉ kết chuyển `min(oldBalance, newDeposit)` rồi thôi, phần dư nằm im trên hợp đồng cũ. Bạn muốn **tự động hoàn phần dư** hay **để admin xử lý thủ công** qua addDepositTransaction?

> **❓ Q2 — Điểm #3 (Chuyển phòng):**
>
> Khi chuyển phòng, `manual_refund_amount` hiện tại `payment_method` không rõ (tiền mặt hay chuyển khoản?). Nên mặc định là `Tiền mặt` hay để admin xử lý sau?
