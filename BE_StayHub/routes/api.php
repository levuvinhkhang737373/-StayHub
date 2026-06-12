<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AssetTemplateController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\MaintenanceRequestController as AdminMaintenanceController;
use App\Http\Controllers\Admin\MeterController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Tenant\AuthController as TenantAuthController;
use App\Http\Controllers\Tenant\MaintenanceRequestController as TenantMaintenanceController;
use App\Http\Controllers\Tenant\NotificationController as TenantNotificationController;
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

        // =========================Regions================================
        Route::patch('regions/{region}/status', [RegionController::class, 'updateStatus']);
        Route::apiResource('regions', RegionController::class);

        // =========================Buildings================================
        Route::patch('buildings/{building}/status', [BuildingController::class, 'updateStatus']);
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

        // =========================Expense Categories================================
        Route::patch('expense-categories/{expenseCategory}/status', [ExpenseCategoryController::class, 'updateStatus']);
        Route::apiResource('expense-categories', ExpenseCategoryController::class);

        // =========================Settings================================
        Route::patch('settings/{setting}/toggle-public', [SettingController::class, 'togglePublic']);
        Route::apiResource('settings', SettingController::class);

        // =========================Admin Accounts================================
        Route::patch('accounts/{account}/status', [AdminAccountController::class, 'updateStatus']);
        Route::apiResource('accounts', AdminAccountController::class);

        // =========================Tenants================================
        Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus']);
        Route::apiResource('tenants', TenantController::class);
        // =========================Vehicles================================
        Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'updateStatus']);
        Route::apiResource('vehicles', VehicleController::class);

        // =========================Maintenance Requests====================
        Route::patch('maintenance-requests/{id}/assign', [AdminMaintenanceController::class, 'assign']);
        Route::patch('maintenance-requests/{id}/status', [AdminMaintenanceController::class, 'updateStatus']);
        Route::apiResource('maintenance-requests', AdminMaintenanceController::class)->only(['index', 'show']);

        // =========================Notifications===========================
        Route::apiResource('notifications', AdminNotificationController::class);

        // =========================Contracts================================
        Route::get('contracts/available-rooms', [ContractController::class, 'availableRooms']);
        Route::patch('contracts/{contract}/status', [ContractController::class, 'updateStatus']);
        Route::post('contracts/{contract}/renew', [ContractController::class, 'renew']);
        Route::post('contracts/{contract}/deposit-transactions', [ContractController::class, 'addDepositTransaction']);
        Route::apiResource('contracts', ContractController::class);

        //==========================Rooms===================================
        Route::apiResource('/room', RoomController::class);
        Route::patch('/room/{id}/status', [RoomController::class, 'updateStatus']);
    });
});

// =========================Tenant API Group=========================
Route::prefix('tenant')->group(function (): void {
    Route::post('/login', [TenantAuthController::class, 'login']);

    Route::middleware(['auth.tenant'])->group(function (): void {
        Route::get('/me', [TenantAuthController::class, 'me']);
        Route::post('/logout', [TenantAuthController::class, 'logout']);

        // =========================Maintenance=========================
        Route::post('maintenance-requests/{id}/feedback', [TenantMaintenanceController::class, 'feedback']);
        Route::apiResource('maintenance-requests', TenantMaintenanceController::class)->only(['index', 'store', 'show']);

        // =========================Notifications=======================
        Route::get('notifications', [TenantNotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [TenantNotificationController::class, 'read']);
    });
});

// =========================Webhooks API Group=========================
Route::post('sepay-webhook', [SePayWebhookController::class, 'handle']);

