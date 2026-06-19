<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Requests\Admin\Invoice\GenerateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\BulkInvoiceGenerated;

class BulkGenerateInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $buildingId;
    public int $billingMonth;
    public int $billingYear;
    public int $adminId;

    public function __construct(int $buildingId, int $billingMonth, int $billingYear, int $adminId)
    {
        $this->buildingId = $buildingId;
        $this->billingMonth = $billingMonth;
        $this->billingYear = $billingYear;
        $this->adminId = $adminId;
    }

    public function handle(): void
    {
        $admin = Admin::find($this->adminId);
        if (!$admin) return;

        $contracts = Contract::query()
            ->whereHas('room', function ($q) {
                $q->where('building_id', $this->buildingId);
            })
            ->whereIn('status', [Contract::STATUS_ACTIVE, Contract::STATUS_EXPIRED])
            ->whereDoesntHave('invoices', function ($q) {
                $q->where('billing_month', $this->billingMonth)
                  ->where('billing_year', $this->billingYear);
            })
            ->get();

        $successCount = 0;
        $errorCount = 0;

        $controller = app(InvoiceController::class);

        foreach ($contracts as $contract) {
            try {
                $request = GenerateRequest::create('/api/v1/admin/invoices/generate', 'POST', [
                    'contract_id' => $contract->id,
                    'billing_month' => $this->billingMonth,
                    'billing_year' => $this->billingYear,
                ]);
                $request->setContainer(app());
                $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
                $request->setUserResolver(function () use ($admin) {
                    return $admin;
                });
                $request->validateResolved();

                $response = $controller->generate($request);

                if ($response->getStatusCode() === 201) {
                    $successCount++;
                } else {
                    $errorCount++;
                    Log::warning('Bulk invoice failed for contract ' . $contract->id . ': ' . $response->getContent());
                }
            } catch (\Throwable $e) {
                Log::error('BulkGenerateInvoicesJob error: ' . $e->getMessage());
                $errorCount++;
            }
        }

        event(new BulkInvoiceGenerated($this->buildingId, $this->billingMonth, $this->billingYear, $successCount, $errorCount, $admin->id));
    }
}
