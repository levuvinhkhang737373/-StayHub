<?php

namespace App\Http\Controllers\Webhook;

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
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        // Only process incoming transfers ("in")
        $transferType = $request->input('transferType');
        if ($transferType && strtolower($transferType) !== 'in') {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Only incoming transfers are processed.'
            ]);
        }

        // Read transaction details
        $amount = (float) $request->input('amount');
        if ($amount <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction amount must be positive.'
            ], 400);
        }

        $content = $request->input('content') ?? $request->input('transferDesc') ?? '';
        $reference = $request->input('code'); // Bank transaction reference code
        $sepayId = $request->input('id'); // SePay ID
        
        // Use bank reference code first, fallback to SePay transaction ID
        $transactionReference = !empty($reference) ? $reference : $sepayId;

        if (empty($transactionReference)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing transaction reference code.'
            ], 400);
        }

        // Check for duplicate transaction reference to ensure idempotency
        $existingTransaction = ContractDepositTransaction::where('transaction_reference', $transactionReference)->first();
        if ($existingTransaction) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Transaction already processed.'
            ]);
        }

        // Extract contract code using regex matching COC <contract_code>
        $contractCode = null;
        if (preg_match('/COC\s+([A-Za-z0-9_\-\.]+)/i', $content, $matches)) {
            $contractCode = $matches[1];
        }

        if (empty($contractCode)) {
            Log::warning("SePay Webhook: Contract code could not be extracted from content: {$content}");
            return response()->json([
                'status' => 'error',
                'message' => 'Could not extract contract code from transaction description.'
            ], 400);
        }

        // Find the contract
        $contract = Contract::where('contract_code', $contractCode)->first();
        if (!$contract) {
            Log::warning("SePay Webhook: Contract not found for code: {$contractCode}");
            return response()->json([
                'status' => 'error',
                'message' => "Contract with code {$contractCode} not found."
            ], 404);
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
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process deposit transaction.'
            ], 500);
        }

        Log::info("SePay Webhook: Successfully processed payment for contract {$contractCode}, amount: {$amount}");

        return response()->json([
            'status' => 'success',
            'message' => 'Payment processed successfully.'
        ]);
    }
}
