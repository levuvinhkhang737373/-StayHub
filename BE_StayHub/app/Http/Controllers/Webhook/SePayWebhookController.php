<?php

namespace App\Http\Controllers\Webhook;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SePayWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        try {
            Log::info('SePay Webhook received request:', $request->all());

            $token = config('services.sepay.webhook_token');
            if (!empty($token)) {
                $authHeader = $request->header('Authorization');
                if ($authHeader !== $token && $authHeader !== 'Apikey ' . $token) {
                    Log::warning('SePay Webhook unauthorized request.');
                    return ApiResponse::responseJson(false, 'Yêu cầu không được xác thực.', 401, null, 401);
                }
            }

            $transferType = $request->input('transferType');
            if ($transferType && strtolower($transferType) !== 'in') {
                return ApiResponse::responseJson(true, 'Chỉ xử lý giao dịch nhận tiền.', 0, ['status' => 'ignored'], 200);
            }

            $content = $request->input('content') ?? $request->input('transferDesc') ?? '';
            $reference = $request->input('code');
            $sepayId = $request->input('id');

            if ($reference === 'SEPAYTEST' || $content === 'SEPAY TEST WEBHOOK') {
                Log::info('SePay Webhook: Test request bypassed successfully.');
                return ApiResponse::responseJson(true, 'Nhận webhook thử nghiệm thành công.', 0, null, 200);
            }

            $amount = (float) ($request->input('amount') ?? $request->input('transferAmount') ?? 0);
            if ($amount <= 0) {
                return ApiResponse::responseJson(false, 'Số tiền giao dịch phải lớn hơn 0.', 400, null, 400);
            }
            
            $transactionReference = $reference ?: $sepayId;
            if (empty($transactionReference)) {
                return ApiResponse::responseJson(false, 'Thiếu mã tham chiếu giao dịch.', 400, null, 400);
            }

            $existingTransaction = ContractDepositTransaction::where('transaction_reference', $transactionReference)->first();
            if ($existingTransaction) {
                return ApiResponse::responseJson(true, 'Giao dịch đã được xử lý.', 0, ['status' => 'ignored'], 200);
            }

            $contract = null;
            $contractCode = null;

            if (preg_match('/(HD-[A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
                $contractCode = $matches[1];
            }

            if (empty($contractCode) && preg_match('/COC\s+([A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
                $contractCode = $matches[1];
            }

            if (!empty($contractCode)) {
                $contract = Contract::where('contract_code', $contractCode)->first();
            }

            if (!$contract) {
                $cleanContent = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $content));

                $pendingContracts = Contract::where('payment_status', Contract::PAYMENT_STATUS_PENDING)->get();
                foreach ($pendingContracts as $pc) {
                    $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pc->contract_code));
                    if (!empty($cleanCode) && str_contains($cleanContent, $cleanCode)) {
                        $contract = $pc;
                        $contractCode = $pc->contract_code;
                        break;
                    }
                }

                if (!$contract) {
                    $recentContracts = Contract::orderBy('id', 'desc')->take(200)->get();
                    foreach ($recentContracts as $rc) {
                        $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rc->contract_code));
                        if (!empty($cleanCode) && str_contains($cleanContent, $cleanCode)) {
                            $contract = $rc;
                            $contractCode = $rc->contract_code;
                            break;
                        }
                    }
                }
            }

            if (!$contract) {
                Log::warning("SePay Webhook: Contract not found for content: {$content}");
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng hoặc không thể trích xuất mã hợp đồng từ nội dung giao dịch.', 404, null, 404);
            }

            DB::transaction(function () use ($contract, $amount, $transactionReference, $content) {
                ContractDepositTransaction::create([
                    'contract_id' => $contract->id,
                    'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                    'amount' => $amount,
                    'transaction_date' => now()->toDateString(),
                    'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                    'transaction_reference' => $transactionReference,
                    'note' => 'Hệ thống tự động ghi nhận qua SePay Webhook. Nội dung gốc: ' . $content,
                    'created_by' => null,
                ]);

                if ($contract->status === Contract::STATUS_CANCELLED) {
                    $hasOtherActiveContract = Contract::where('room_id', $contract->room_id)
                        ->where('id', '!=', $contract->id)
                        ->where('status', Contract::STATUS_ACTIVE)
                        ->exists();

                    if (!$hasOtherActiveContract) {
                        $contract->status = Contract::STATUS_ACTIVE;
                        $contract->note = ($contract->note ? $contract->note . "\n" : "") . "[Hệ thống] Tự động khôi phục hợp đồng và kích hoạt lại do đã nhận được tiền cọc qua SePay.";
                        $contract->save();

                        $contract->contractTenants()->update(['is_staying' => true]);
                        $contract->contractVehicles()->update(['is_active' => true]);

                        if ($contract->room_id) {
                            $occupants = \App\Models\ContractTenant::query()
                                ->where('is_staying', true)
                                ->whereNull('leave_date')
                                ->whereHas('contract', fn ($query) => $query->where('room_id', $contract->room_id)->where('status', Contract::STATUS_ACTIVE))
                                ->distinct('tenant_id')
                                ->count('tenant_id');

                            \App\Models\Room::query()->whereKey($contract->room_id)->update(['current_occupants' => $occupants]);
                        }

                        Log::info("SePay Webhook: Restored auto-cancelled contract {$contract->contract_code} as deposit paid successfully.");
                    } else {
                        $contract->note = ($contract->note ? $contract->note . "\n" : "") . "[Hệ thống] Nhận được tiền cọc " . number_format($amount, 0, ',', '.') . " VND qua SePay nhưng KHÔNG THỂ tự động khôi phục hợp đồng vì phòng này đã có hợp đồng hoạt động khác. Vui lòng hoàn tiền hoặc xử lý thủ công.";
                        $contract->save();

                        Log::warning("SePay Webhook: Received payment for auto-cancelled contract {$contract->contract_code} but room {$contract->room_id} is already occupied.");
                    }
                }
            });

            Log::info("SePay Webhook: Successfully processed payment for contract {$contractCode}, amount: {$amount}");

            return ApiResponse::responseJson(true, 'Xử lý thanh toán thành công.', 0, null, 200);

        } catch (\Exception $e) {
            Log::error('SePay Webhook transaction error: ' . $e->getMessage());
            return ApiResponse::responseJson(false, 'Lỗi hệ thống: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
