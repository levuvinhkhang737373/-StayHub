<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class CancelExpiredDepositContracts extends Command
{
    protected $signature = 'contracts:cancel-expired-deposits';
    protected $description = 'Quét và tự động hủy các hợp đồng chưa đóng cọc quá 1 ngày để giải phóng phòng.';

    public function handle()
    {
        $cutoffTime = now('Asia/Ho_Chi_Minh')
            ->subDay()
            ->setTimezone(config('app.timezone', 'UTC'));

        // Lấy danh sách hợp đồng Đang hiệu lực, chưa đóng cọc thành công, tạo trước 1 ngày.
        $contracts = Contract::query()
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('payment_status', '!=', Contract::PAYMENT_STATUS_SUCCESS)
            ->where('created_at', '<=', $cutoffTime)
            ->get();

        $cancelledCount = 0;

        foreach ($contracts as $contract) {
            // Sử dụng accessor is_deposit_paid để kiểm tra lại một lần nữa cho chắc chắn
            if (!$contract->is_deposit_paid) {
                DB::transaction(function () use ($contract, &$cancelledCount) {
                    // Cập nhật trạng thái hợp đồng thành Đã hủy (4) và trạng thái thanh toán thành Hết hạn (4)
                    $contract->status = Contract::STATUS_CANCELLED;
                    $contract->payment_status = Contract::PAYMENT_STATUS_EXPIRED;
                    $contract->note = ($contract->note ? $contract->note . "\n" : "") . "[Hệ thống] Tự động hủy hợp đồng do quá hạn 1 ngày chưa đóng tiền cọc.";
                    $contract->save();

                    // Tắt trạng thái ở của khách thuê và xe trong hợp đồng này
                    $contract->contractTenants()->update(['is_staying' => false]);
                    $contract->contractVehicles()->update(['is_active' => false]);

                    // Cập nhật lại số người đang ở thực tế trong phòng
                    if ($contract->room_id) {
                        $occupants = ContractTenant::query()
                            ->where('is_staying', true)
                            ->whereNull('leave_date')
                            ->whereHas('contract', fn (Builder $query) => $query->where('room_id', $contract->room_id)->where('status', Contract::STATUS_ACTIVE))
                            ->distinct('tenant_id')
                            ->count('tenant_id');

                        Room::query()->whereKey($contract->room_id)->update(['current_occupants' => $occupants]);
                    }

                    $this->info("Đã hủy hợp đồng {$contract->contract_code} do quá hạn 1 ngày không đóng cọc.");
                    $cancelledCount++;
                });
            }
        }

        $this->info("Hoàn tất quét hợp đồng cọc quá hạn. Đã hủy {$cancelledCount} hợp đồng.");
        return 0;
    }
}
