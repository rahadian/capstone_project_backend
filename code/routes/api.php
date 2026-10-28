<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UnitBlockController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::apiResource('users', UserController::class);

    Route::apiResource('tenants', TenantController::class);

    Route::get('units/{unit}/blocks', [UnitBlockController::class, 'index']);
    Route::post('units/{unit}/blocks', [UnitBlockController::class, 'store']);
    Route::delete('units/{unit}/blocks/{block}', [UnitBlockController::class, 'destroy']);
    Route::patch('units/{id}/status', [UnitController::class, 'updateStatus']);

    Route::apiResource('units', UnitController::class);

    Route::get('contracts/export', [ContractController::class, 'export']);
    Route::apiResource('contracts', ContractController::class);

    Route::get('payments/export', [PaymentController::class, 'export']);
    Route::apiResource('payments', PaymentController::class);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
});

Route::get('list/units', [UnitController::class, 'list_nologin']);
Route::get('detail/unit/{unit}', [UnitController::class, 'show_nologin']);
