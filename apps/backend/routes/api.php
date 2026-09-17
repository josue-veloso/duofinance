<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClearingController;
use App\Http\Controllers\CoupleController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\RecurringExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/couple', [CoupleController::class, 'show']);
    Route::post('/couple', [CoupleController::class, 'store']);

    Route::middleware('couple')->group(function () {
        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Expenses
        Route::get('/expenses/{month}', [ExpenseController::class, 'indexByMonth']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
        Route::post('/expenses/{id}/undo', [ExpenseController::class, 'undo']);
        Route::post('/expenses/{id}/restore', [ExpenseController::class, 'restore']);
        Route::post('/expenses/{id}/approve', [ExpenseController::class, 'approve']);
        Route::post('/expenses/{id}/reject', [ExpenseController::class, 'reject']);
        Route::get('/expenses/{id}/audits', [ExpenseController::class, 'audits']);
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

        // Recurring Expenses
        Route::get('/recurring-expenses', [RecurringExpenseController::class, 'index']);
        Route::post('/recurring-expenses', [RecurringExpenseController::class, 'store']);
        Route::patch('/recurring-expenses/{id}', [RecurringExpenseController::class, 'updateStatus']);

        // Clearing & Debt Management
        Route::get('/clearing/history', [ClearingController::class, 'history']);
        Route::get('/clearing/debts', [ClearingController::class, 'debts']);
        Route::post('/clearing/debts/payments', [ClearingController::class, 'registerPayment']);
        Route::get('/clearing/{month}', [ClearingController::class, 'show']);
        Route::post('/clearing/{month}/close', [ClearingController::class, 'close']);
    });
});
