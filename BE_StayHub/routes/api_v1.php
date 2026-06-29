<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\AssetTemplateController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\BulkGenerateInvoiceController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\MaintenanceRequestController as AdminMaintenanceController;
use App\Http\Controllers\Admin\MeterController;
use App\Http\Controllers\Admin\MeterReadingController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomMovementController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\FireSafetyAlertController;
use App\Http\Controllers\Admin\SecurityCameraController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
use App\Http\Controllers\Tenant\ChatController as TenantChatController;
use App\Http\Controllers\Tenant\InvoiceController as TenantInvoiceController;
use App\Http\Controllers\Tenant\MaintenanceRequestController as TenantMaintenanceController;
use App\Http\Controllers\Tenant\NotificationController as TenantNotificationController;
use App\Http\Controllers\Tenant\ForgotPasswordController;
use App\Http\Controllers\Webhook\SePayWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/face-login', [AuthController::class, 'faceLogin']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['auth.admin'])->group(function (): void {

        // =========================Auth================================
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/face-id/register', [AuthController::class, 'registerFaceId']);
        Route::delete('/face-id', [AuthController::class, 'deleteFaceId']);
        Route::patch('/password', [AuthController::class, 'changePassword']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);

        // =========================Regions================================
        Route::patch('regions/{region}/status', [RegionController::class, 'updateStatus']);
        Route::apiResource('regions', RegionController::class);

        // =========================Buildings================================
        Route::patch('buildings/{building}/status', [BuildingController::class, 'updateStatus']);
        Route::put('buildings/{building}/utility-prices', [BuildingController::class, 'updateUtilityPrices']);
        Route::get('buildings/{building}/utility-price-history', [BuildingController::class, 'utilityPriceHistory']);
        Route::apiResource('buildings', BuildingController::class);

        // =========================Asset Templates================================
        Route::patch('asset-templates/{assetTemplate}/status', [AssetTemplateController::class, 'updateStatus']);
        Route::apiResource('asset-templates', AssetTemplateController::class);

        // =========================Rooms Types================================
        Route::patch('room-types/{roomType}/status', [RoomTypeController::class, 'updateStatus']);
        Route::apiResource('room-types', RoomTypeController::class);

        // =========================Services================================
        Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus']);
        Route::apiResource('services', ServiceController::class);

        // =========================Meter Devices================================
        Route::patch('meter-devices/{meterDevice}/status', [MeterController::class, 'updateStatus']);
        Route::apiResource('meter-devices', MeterController::class);

        // =========================Meter Readings ==============================
        Route::get('meter-readings/init', [MeterReadingController::class, 'init']);
        Route::post('meter-readings/analyze-image', [MeterReadingController::class, 'analyzeImage']);
        Route::post('meter-readings', [MeterReadingController::class, 'store']);

        // =========================Expense Categories================================
        Route::patch('expense-categories/{expenseCategory}/status', [ExpenseCategoryController::class, 'updateStatus']);
        Route::apiResource('expense-categories', ExpenseCategoryController::class);

        // =========================Expenses================================
        Route::patch('expenses/{expense}/cancel', [ExpenseController::class, 'cancel']);
        Route::apiResource('expenses', ExpenseController::class);

        // =========================Settings================================
        Route::patch('settings/{setting}/toggle-public', [SettingController::class, 'togglePublic']);
        Route::apiResource('settings', SettingController::class);

        // =========================Admin Accounts================================
        Route::patch('accounts/{account}/status', [AdminAccountController::class, 'updateStatus']);
        Route::apiResource('accounts', AdminAccountController::class);

        // =========================Admin Activity Logs================================
        Route::middleware(['auth.admin:2'])->group(function (): void {
            Route::get('activity-logs', [AdminLogController::class, 'index']);
            Route::get('activity-logs/{adminLog}', [AdminLogController::class, 'show']);
        });

        // =========================Tenants================================
        Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus']);
        Route::apiResource('tenants', TenantController::class);
        // =========================Vehicles================================
        Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
        Route::apiResource('vehicles', VehicleController::class);

        // =========================Maintenance Requests====================
        Route::patch('maintenance-requests/{id}/status', [AdminMaintenanceController::class, 'updateStatus']);
        Route::apiResource('maintenance-requests', AdminMaintenanceController::class)->only(['index', 'show']);

        // =========================Notifications===========================
        Route::apiResource('notifications', AdminNotificationController::class);

        // =========================Chat====================================
        Route::get('chat/conversations', [AdminChatController::class, 'index']);
        Route::get('chat/conversations/{conversation}/messages', [AdminChatController::class, 'messages']);
        Route::post('chat/conversations/{conversation}/messages', [AdminChatController::class, 'sendMessage'])->middleware('throttle:chat-send');
        Route::patch('chat/conversations/{conversation}/read', [AdminChatController::class, 'markAsRead']);

        // =========================Contracts================================
        Route::get('contracts/available-rooms', [ContractController::class, 'availableRooms']);
        Route::patch('contracts/{contract}/status', [ContractController::class, 'updateStatus']);
        Route::post('contracts/{contract}/terminate', [ContractController::class, 'terminate']);
        Route::post('contracts/{contract}/renew', [ContractController::class, 'renew']);
        Route::get('contracts/{contract}/available-tenants', [ContractController::class, 'availableTenants']);
        Route::post('contracts/{contract}/tenants', [ContractController::class, 'addTenant']);
        Route::post('contracts/{contract}/deposit-transactions', [ContractController::class, 'addDepositTransaction']);
        Route::apiResource('contracts', ContractController::class);

        // =========================Invoices================================
        Route::post('buildings/{building}/invoices/bulk-generate', [BulkGenerateInvoiceController::class, '__invoke']);
        Route::post('invoices/preview', [AdminInvoiceController::class, 'preview']);
        Route::post('invoices/generate', [AdminInvoiceController::class, 'generate']);
        Route::post('invoices/{invoice}/payments', [AdminInvoiceController::class, 'recordPayment']);
        Route::post('invoices/{invoice}/payments/{payment}/confirm', [AdminInvoiceController::class, 'confirmPayment']);
        Route::patch('invoices/{invoice}/cancel', [AdminInvoiceController::class, 'cancel']);
        Route::apiResource('invoices', AdminInvoiceController::class)->only(['index', 'show', 'update']);

        // ==========================Rooms===================================
        Route::apiResource('rooms', RoomController::class);
        Route::patch('rooms/{id}/status', [RoomController::class, 'updateStatus']);
        Route::get('room-movements', [RoomMovementController::class, 'index']);
        Route::get('room-movements/{roomMovement}', [RoomMovementController::class, 'show']);
        Route::post('room-transfers/tenant', [RoomController::class, 'transferTenant']);

        // ==========================Financials==============================
        Route::get('financials/report', [FinancialReportController::class, 'index']);

        // ==========================Dashboard===============================
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);
        Route::get('dashboard/utility-price-history', [DashboardController::class, 'utilityPriceHistory']);

        // ==========================AI Camera Fire Safety===================
        Route::post('security-cameras/{securityCamera}/analyze', [SecurityCameraController::class, 'analyze']);
        Route::post('security-cameras/{securityCamera}/test-stream', [SecurityCameraController::class, 'testStream']);
        Route::apiResource('security-cameras', SecurityCameraController::class)->parameters(['security-cameras' => 'securityCamera']);
        Route::get('fire-safety-alerts', [FireSafetyAlertController::class, 'index']);
        Route::get('fire-safety-alerts/{fireSafetyAlert}', [FireSafetyAlertController::class, 'show']);
        Route::patch('fire-safety-alerts/{fireSafetyAlert}/acknowledge', [FireSafetyAlertController::class, 'acknowledge']);
        Route::patch('fire-safety-alerts/{fireSafetyAlert}/resolve', [FireSafetyAlertController::class, 'resolve']);
        Route::patch('fire-safety-alerts/{fireSafetyAlert}/false-alarm', [FireSafetyAlertController::class, 'markFalseAlarm']);
    });
});

// =========================Tenant API Group=========================
Route::prefix('tenant')->group(function (): void {
    Route::post('/login', [TenantAuthController::class, 'login']);
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetCodeEmail']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

    Route::middleware(['auth.tenant'])->group(function (): void {
        Route::get('/me', [TenantAuthController::class, 'me']);
        Route::patch('/profile', [TenantAuthController::class, 'updateProfile']);
        Route::post('/logout', [TenantAuthController::class, 'logout']);
        Route::get('utility-price-history', [TenantAuthController::class, 'utilityPriceHistory']);
        Route::get('building-settings', [TenantAuthController::class, 'buildingSettings']);

        // =========================Maintenance=========================
        Route::post('maintenance-requests/{id}/feedback', [TenantMaintenanceController::class, 'feedback']);
        Route::apiResource('maintenance-requests', TenantMaintenanceController::class)->only(['index', 'store', 'show']);

        // =========================Notifications=======================
        Route::get('notifications', [TenantNotificationController::class, 'index']);
        Route::post('notifications/read-all', [TenantNotificationController::class, 'readAll']);
        Route::post('notifications/{id}/read', [TenantNotificationController::class, 'read']);

        // =========================Chat===============================
        Route::get('chat/conversation', [TenantChatController::class, 'conversation']);
        Route::get('chat/messages', [TenantChatController::class, 'messages']);
        Route::post('chat/messages', [TenantChatController::class, 'sendMessage'])->middleware('throttle:chat-send');
        Route::patch('chat/read', [TenantChatController::class, 'markAsRead']);

        // =========================Contract============================
        Route::get('contract', [App\Http\Controllers\Tenant\ContractController::class, 'show']);
        Route::get('contracts', [App\Http\Controllers\Tenant\ContractController::class, 'index']);
        Route::post('contracts/{id}/sign', [App\Http\Controllers\Tenant\ContractController::class, 'sign']);

        // =========================Invoices============================
        Route::get('invoices', [TenantInvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [TenantInvoiceController::class, 'show']);
        Route::post('invoices/{invoice}/payment-proof', [TenantInvoiceController::class, 'uploadProof']);
    });
});

// =========================Webhooks API Group=========================
Route::post('sepay-webhook', [SePayWebhookController::class, 'handle']);
