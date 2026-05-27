<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AssetTemplateController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/face-login', [AuthController::class, 'faceLogin']);

    Route::middleware(['auth.admin'])->group(function (): void {

        //=========================Auth================================
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/face-id/register', [AuthController::class, 'registerFaceId']);
        Route::delete('/face-id', [AuthController::class, 'deleteFaceId']);
        Route::patch('/password', [AuthController::class, 'changePassword']);

        //=========================Regions================================
        Route::patch('regions/{region}/status', [RegionController::class, 'updateStatus']);
        Route::apiResource('regions', RegionController::class);

        //=========================Buildings================================
        Route::patch('buildings/{building}/status', [BuildingController::class, 'updateStatus']);
        Route::apiResource('buildings', BuildingController::class);

        //=========================Asset Templates================================
        Route::patch('asset-templates/{assetTemplate}/status', [AssetTemplateController::class, 'updateStatus']);
        Route::apiResource('asset-templates', AssetTemplateController::class);

        //=========================Rooms Types================================
        Route::patch('room-types/{roomType}/status', [RoomTypeController::class, 'updateStatus']);
        Route::apiResource('room-types', RoomTypeController::class);

        //=========================Services================================
        Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus']);
        Route::apiResource('services', ServiceController::class);

        //=========================Expense Categories================================
        Route::patch('expense-categories/{expenseCategory}/status', [ExpenseCategoryController::class, 'updateStatus']);
        Route::apiResource('expense-categories', ExpenseCategoryController::class);

        //=========================Settings================================
        Route::apiResource('settings', SettingController::class);

        //=========================Admin Accounts================================
        Route::patch('accounts/{account}/status', [AdminAccountController::class, 'updateStatus']);
        Route::apiResource('accounts', AdminAccountController::class);

        //=========================Contracts================================
    });
});
