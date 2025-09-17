<?php

use App\Http\Controllers\ExternalPaymentController;
use App\Http\Controllers\ExternalPaymentAdminController;
use App\Http\Controllers\ExternalPaymentPublicController;
use App\Http\Middleware\PaymentRoleMiddleware;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Payments Routes
|--------------------------------------------------------------------------
|
| Rutas para el módulo de pagos externos completamente independiente.
| Incluye rutas para usuarios con rol payment, admins, y páginas públicas.
|
*/

// ================================
// RUTAS PÚBLICAS (sin autenticación)
// ================================
Route::prefix('pay')->group(function () {
    // Mostrar información del link de pago
    Route::get('{linkCode}', [ExternalPaymentPublicController::class, 'show']);

    // Iniciar pago con Datafast
    Route::post('{linkCode}/datafast', [ExternalPaymentPublicController::class, 'initiateDatafastPayment']);

    // Iniciar pago con Deuna
    Route::post('{linkCode}/deuna', [ExternalPaymentPublicController::class, 'initiateDeunaPayment']);

    // Verificar resultado de pago Datafast
    Route::post('{linkCode}/datafast/verify', [ExternalPaymentPublicController::class, 'verifyDatafastPayment']);

    // Webhook para Deuna
    Route::post('{linkCode}/deuna/webhook', [ExternalPaymentPublicController::class, 'deunaWebhook']);
});

// ================================
// RUTAS PARA USUARIOS PAYMENT
// ================================
Route::prefix('payment')->middleware(['auth:api', PaymentRoleMiddleware::class])->group(function () {
    // Dashboard del usuario payment
    Route::get('dashboard', [ExternalPaymentController::class, 'dashboard']);

    // CRUD de links del usuario actual
    Route::get('links', [ExternalPaymentController::class, 'index']);
    Route::post('links', [ExternalPaymentController::class, 'store']);
    Route::get('links/{id}', [ExternalPaymentController::class, 'show']);
    Route::patch('links/{id}/cancel', [ExternalPaymentController::class, 'cancel']);
});

// ================================
// RUTAS PARA ADMINISTRADORES
// ================================
Route::prefix('admin/external-payments')->middleware(['auth:api', AdminMiddleware::class])->group(function () {
    // Dashboard global de pagos externos
    Route::get('dashboard', [ExternalPaymentAdminController::class, 'dashboard']);

    // Ver TODOS los links de pago
    Route::get('links', [ExternalPaymentAdminController::class, 'index']);
    Route::get('links/{id}', [ExternalPaymentAdminController::class, 'show']);
    Route::patch('links/{id}/cancel', [ExternalPaymentAdminController::class, 'cancel']);

    // Obtener usuarios con rol payment
    Route::get('users', [ExternalPaymentAdminController::class, 'getPaymentUsers']);

    // Estadísticas por fechas
    Route::get('stats', [ExternalPaymentAdminController::class, 'stats']);
});

// ================================
// RUTAS DE TESTING (solo en desarrollo)
// ================================
if (config('app.env') !== 'production') {
    Route::prefix('external-payments/test')->group(function () {
        // Endpoint para simular webhooks en desarrollo
        Route::post('webhook/{linkCode}', function ($linkCode) {
            return response()->json([
                'success' => true,
                'message' => 'Test webhook received',
                'link_code' => $linkCode,
                'timestamp' => now()->toISOString(),
            ]);
        });
    });
}