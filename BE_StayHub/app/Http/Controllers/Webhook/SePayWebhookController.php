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
        Log::info('SePay Webhook received request:', $request->all());

        // Validate webhook token if configured
        $token = config('services.sepay.webhook_token');
        if (!empty($token)) {
            $authHeader = $request->header('Authorization');
            $isValid = false;
            if ($authHeader === $token || $authHeader === 'Apikey ' . $token) {
                $isValid = true;
            }
            if (!$isValid) {
                Log::warning('SePay Webhook unauthorized request.');
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }
        }

        // Only process incoming transfers ("in")
        $transferType = $request->input('transferType');
        if ($transferType && strtolower($transferType) !== 'in') {
            return ApiResponse::responseJson(true, 'Only incoming transfers are processed.', 0, ['status' => 'ignored'], 200);
        }

        $content = $request->input('content') ?? $request->input('transferDesc') ?? '';
        $reference = $request->input('code'); // Bank transaction reference code
        $sepayId = $request->input('id'); // SePay ID

        // Check if it's a SePay test webhook delivery
        if ($reference === 'SEPAYTEST' || $content === 'SEPAY TEST WEBHOOK') {
            Log::info('SePay Webhook: Test request bypassed successfully.');
            return ApiResponse::responseJson(true, 'Test webhook received successfully.', 0, null, 200);
        }

        // Read transaction details
        $amount = (float) ($request->input('amount') ?? $request->input('transferAmount') ?? 0);
        if ($amount <= 0) {
            return ApiResponse::responseJson(false, 'Transaction amount must be positive.', 400, null, 400);
        }
        
        // Use bank reference code first, fallback to SePay transaction ID
        $transactionReference = !empty($reference) ? $reference : $sepayId;

        if (empty($transactionReference)) {
            return ApiResponse::responseJson(false, 'Missing transaction reference code.', 400, null, 400);
        }

        // Check for duplicate transaction reference to ensure idempotency
        $existingTransaction = ContractDepositTransaction::where('transaction_reference', $transactionReference)->first();
        if ($existingTransaction) {
            return ApiResponse::responseJson(true, 'Transaction already processed.', 0, ['status' => 'ignored'], 200);
        }

        // Extract contract code using regex matching COC <contract_code>
        $contractCode = null;
        if (preg_match('/COC\s+([A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
            $contractCode = $matches[1];
        }

        if (empty($contractCode)) {
            Log::warning("SePay Webhook: Contract code could not be extracted from content: {$content}");
            return ApiResponse::responseJson(false, 'Could not extract contract code from transaction description.', 400, null, 400);
        }

        // Find the contract
        $contract = Contract::where('contract_code', $contractCode)->first();
        if (!$contract) {
            Log::warning("SePay Webhook: Contract not found for code: {$contractCode}");
            return ApiResponse::responseJson(false, "Contract with code {$contractCode} not found.", 404, null, 404);
        }

        // Create the transaction
        try {
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
            });
        } catch (\Exception $e) {
            Log::error('SePay Webhook transaction error: ' . $e->getMessage());
            return ApiResponse::responseJson(false, 'Failed to process deposit transaction.', 500, null, 500);
        }

        Log::info("SePay Webhook: Successfully processed payment for contract {$contractCode}, amount: {$amount}");

        return ApiResponse::responseJson(true, 'Payment processed successfully.', 0, null, 200);
    }
}
