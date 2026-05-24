<?php

use App\Http\Controllers\Admin\AssetTemplateController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Models\Admin;
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

        Route::middleware('auth.admin:' . Admin::ROLE_BUILDING_MANAGER . ',' . Admin::ROLE_SUPER_ADMIN)->group(function (): void {
            //=========================Regions================================
            Route::patch('regions/{region}/status', [RegionController::class, 'updateStatus']);
            Route::apiResource('regions', RegionController::class);

            //=========================Buildings================================
            Route::apiResource('buildings', BuildingController::class)->only(['index', 'show']);

            Route::middleware('auth.admin:' . Admin::ROLE_SUPER_ADMIN)->group(function (): void {
                Route::patch('buildings/{building}/status', [BuildingController::class, 'updateStatus']);
                Route::apiResource('buildings', BuildingController::class)->except(['index', 'show']);
            });

            //=========================Asset Templates================================
            Route::patch('asset-templates/{assetTemplate}/status', [AssetTemplateController::class, 'updateStatus']);
            Route::apiResource('asset-templates', AssetTemplateController::class);

            //=========================Rooms================================
            Route::patch('room-types/{roomType}/status', [RoomTypeController::class, 'updateStatus']);
            Route::apiResource('room-types', RoomTypeController::class);

            //=========================Contracts================================
        });

    });
});
