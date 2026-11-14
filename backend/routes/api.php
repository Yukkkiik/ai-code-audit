<?php

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::post('/audits', [AuditController::class, 'store']);
    Route::get('/audits', [AuditController::class, 'index'])->middleware('auth');
    Route::get('/audits/{audit:public_id}', [AuditController::class, 'show']);
});
