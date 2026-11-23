<?php

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuditDependencyController;
use App\Http\Controllers\Api\V1\AuditFindingController;
use App\Http\Controllers\Api\V1\AuditMetricController;
use App\Http\Controllers\Api\V1\AuditVulnerabilityController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\NoStoreResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::post('/audits', [AuditController::class, 'store']);
    Route::get('/audits', [AuditController::class, 'index'])->middleware('auth');
    Route::get('/audits/{audit:public_id}', [AuditController::class, 'show']);

    // Hasil SAST dan SCA bersifat privat: tidak boleh di-cache, dan penolakan akses selalu 404 (lihat AuditAccessGuard).
    Route::middleware(NoStoreResponse::class)->group(function () {
        Route::get('/audits/{audit:public_id}/findings', [AuditFindingController::class, 'index']);
        Route::get('/audits/{audit:public_id}/metrics', [AuditMetricController::class, 'index']);
        Route::get('/audits/{audit:public_id}/dependencies', [AuditDependencyController::class, 'index']);
        Route::get('/audits/{audit:public_id}/vulnerabilities', [AuditVulnerabilityController::class, 'index']);
        Route::get('/audits/{audit:public_id}/vulnerabilities/{vulnerability}', [AuditVulnerabilityController::class, 'show']);
    });
});
