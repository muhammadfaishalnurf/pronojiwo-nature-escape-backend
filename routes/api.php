<?php

// ── routes/api.php ── VERSI LENGKAP
// Pastikan seluruh isi routes/api.php seperti ini:

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboard;

// ── PUBLIC ──
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::get('/destinations',      [DestinationController::class, 'index']);
    Route::get('/destinations/{id}', [DestinationController::class, 'show']);
    Route::get('/reviews',           [ReviewController::class, 'index']);

    // Webhook Midtrans — TANPA auth
    Route::post('/payments/notification', [PaymentController::class, 'notification']);
});

// ── AUTH USER ──
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/tickets',  [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);

    Route::post('/reviews', [ReviewController::class, 'store']);

    Route::post('/payments/create',            [PaymentController::class, 'create']);
    Route::post('/payments/check-status',      [PaymentController::class, 'checkStatus']);
    Route::get('/payments/status/{orderId}',   [PaymentController::class, 'status']);
});

// ── ADMIN ──
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index']);

    // Destinations
    Route::get('/destinations',        [DestinationController::class, 'adminIndex']);
    Route::post('/destinations',       [DestinationController::class, 'store']);
    Route::post('/destinations/{id}',  [DestinationController::class, 'update']);
    Route::delete('/destinations/{id}', [DestinationController::class, 'destroy']);

    // Tickets — PENTING: route 'scan' harus SEBELUM route '{id}'
    Route::get('/tickets',                      [TicketController::class, 'adminIndex']);
    Route::get('/tickets/scan/{ticketCode}',    [TicketController::class, 'scan']);      // ← SEBELUM {id}
    Route::patch('/tickets/{id}/status',        [TicketController::class, 'updateStatus']);
    Route::post('/tickets/{id}/use',            [TicketController::class, 'markUsed']);

    // Reviews
    Route::get('/reviews',        [ReviewController::class, 'adminIndex']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

    // Users (admin lihat saja)
    Route::get('/users', [UserController::class, 'index']);
});

// ── SUPER ADMIN ──
Route::prefix('v1/super-admin')->middleware(['auth:sanctum', 'is_super_admin'])->group(function () {
    Route::get('/dashboard',    [SuperAdminDashboard::class, 'index']);
    Route::get('/settings',     [SuperAdminDashboard::class, 'settings']);
    Route::put('/settings',     [SuperAdminDashboard::class, 'updateSettings']);
    Route::get('/super-admin/destinations-list', [UserController::class, 'destinationsList']);
    Route::get('/users',              [UserController::class, 'index']);
    Route::post('/users',             [UserController::class, 'store']);
    Route::put('/users/{id}',         [UserController::class, 'update']);
    Route::delete('/users/{id}',      [UserController::class, 'destroy']);
    Route::post('/users/{id}/assign-role', [UserController::class, 'assignRole']);
});
