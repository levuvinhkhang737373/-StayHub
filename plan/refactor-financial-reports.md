# Kế hoạch Refactor Báo Cáo Lợi Nhuận (Giữ Phiếu Chi, Lọc Báo Cáo)

## Mục tiêu
Duy trì việc tự động sinh Phiếu chi (Expense) khi hoàn cọc để dễ quản lý dòng tiền ra. Tuy nhiên, ở góc độ Báo cáo KQKD (Lợi nhuận), ta sẽ **loại bỏ phiếu chi hoàn cọc** để Lợi nhuận không bị âm vô lý. Đồng thời **bổ sung tiền Phạt/Khấu trừ và Phụ thu** vào Doanh thu để phản ánh đúng hiệu quả kinh doanh.

## Các bước thực hiện chi tiết

### Bước 1: Lọc bỏ Hoàn cọc khỏi Báo cáo Chi phí
Sửa logic truy vấn chi phí trong `FinancialReportController` để nó không cộng các Phiếu chi thuộc danh mục "Hoàn cọc hợp đồng".

**Trong `FinancialReportController.php`:**
```php
private function scopedExpenseQuery(array $buildingIds, bool $includeGlobalExpenses = false): Builder
{
    $query = Expense::query()
        ->where('status', Expense::STATUS_RECORDED)
        ->whereHas('expenseCategory', function(Builder $q) {
            // ✅ Loại trừ hoàn cọc khỏi chi phí kinh doanh
            $q->where('name', '!=', 'Hoàn cọc hợp đồng');
        });

    // ... logic giữ nguyên
}
```

### Bước 2: Bổ sung Khấu trừ cọc vào Doanh thu
Khi thanh lý hoặc chuyển phòng, khách bị trừ cọc (`transaction_type = DEDUCT`). Khoản này thực chất là Doanh Thu đột biến. Ta sẽ cộng thẳng vào tổng doanh thu tháng đó.

**Thêm helper method truy vấn DEDUCT:**
```php
private function scopedDepositDeductionQuery(array $buildingIds): Builder
{
    $query = ContractDepositTransaction::query()
        ->where('transaction_type', ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT);

    if (empty($buildingIds)) {
        return $query->whereRaw('1 = 0');
    }

    return $query->whereHas('contract.room', fn($q) => $q->whereIn('building_id', $buildingIds));
}
```
**Trong logic tính `$revenue` từng tháng:**
```php
$deductions = (float) $this->scopedDepositDeductionQuery($buildingIds)
    ->whereBetween('transaction_date', [$month['start']->toDateString(), $month['end']->toDateString()])
    ->sum('amount');

$revenue += $deductions; // Cộng tiền phạt cọc vào doanh thu
```

### Bước 3: Bổ sung Phí chuyển phòng vào Doanh thu
Khi khách đóng phí chuyển phòng (`extra_amount`), nó nằm trong cột JSON của bảng `RoomMovement`. Ta lôi nó ra cộng vào Doanh thu.

**Thêm helper method truy vấn RoomMovement:**
```php
private function scopedRoomMovementExtraChargeQuery(array $buildingIds): Builder
{
    $query = RoomMovement::query()
        ->whereIn('settlement_payment_status', [RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL, RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID]);

    if (empty($buildingIds)) {
        return $query->whereRaw('1 = 0');
    }

    return $query->whereHas('toRoom', fn($q) => $q->whereIn('building_id', $buildingIds));
}
```
**Cộng dồn vào Doanh thu hàng tháng:**
(Do JSON column có cấu trúc mảng, ta query các RoomMovement phát sinh giao dịch trong tháng, sau đó map mảng JSON để lấy ra tổng tiền `extra_amount` đã thanh toán và cộng vào `$revenue`).

---

## 🎯 Kết quả sau Refactor
1. **Phiếu chi Hoàn cọc vẫn tồn tại:** Bạn dễ dàng xem lại lịch sử trả tiền cho khách.
2. **Lợi nhuận chính xác 100%:** Báo cáo dashboard không bị sập (lỗ) vì hoàn cọc, nhưng lại tăng vọt rất đúng thực tế nhờ có thêm tiền Phạt và tiền Phụ thu được ghi nhận.
3. **Lưu ý nhỏ:** Số tiền "Tổng chi phí" trên Dashboard sẽ thấp hơn Tổng cộng trong màn hình "Danh sách Phiếu chi" vì Dashboard đã tự động giấu đi các khoản tiền Hoàn cọc.

> [!IMPORTANT]
> **Yêu cầu review:** Hướng đi này của bạn quá tuyệt vời. Nếu bạn chốt Plan này, hãy nhấn **Proceed** để tôi tiến hành bắt tay vào sửa code (chỉ cần sửa 1 file duy nhất là `FinancialReportController.php`).
