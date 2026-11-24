<?php

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuditDependencyController;
use App\Http\Controllers\Api\V1\AuditFindingController;
use App\Http\Controllers\Api\V1\AuditMetricController;
use App\Http\Controllers\Api\V1\AuditVulnerabilityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\NoStoreResponse;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    // Grup `api` tidak punya session, padahal `state` OAuth disimpan di session.
    Route::prefix('auth/google')->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class])->group(function () {
        Route::get('/redirect', [AuthController::class, 'redirect']);
        Route::get('/callback', [AuthController::class, 'callback']);
    });

    Route::get('/auth/me', [AuthController::class, 'me'])->middleware(['auth', NoStoreResponse::class]);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');

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
