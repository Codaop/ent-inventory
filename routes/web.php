<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/google', [AuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

Route::middleware('auth')->group(function () {
    Route::get('/', function (\Illuminate\Http\Request $request) {
        $currentUser = $request->attributes->get('currentUser');
        return view('dashboard', compact('currentUser'));
    });

    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{itemId}', [ItemController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/config', [ConfigController::class, 'index']);
    Route::get('/config/{key}', [ConfigController::class, 'show']);
    Route::patch('/config/{key}', [ConfigController::class, 'update']);
    Route::delete('/config/{key}', [ConfigController::class, 'destroy']);

    Route::post('/borrows', [BorrowController::class, 'store']);
    Route::get('/borrows/my', [BorrowController::class, 'my']);
    Route::post('/borrows/{txId}/return', [BorrowController::class, 'return']);

    Route::middleware('admin')->group(function () {
        Route::post('/items', [ItemController::class, 'store']);
        Route::post('/items/{itemId}/adjust', [ItemController::class, 'adjust']);
        Route::patch('/items/{itemId}', [ItemController::class, 'update']);
        Route::delete('/items/{itemId}', [ItemController::class, 'destroy']);

        Route::get('/borrows/pending', [BorrowController::class, 'pending']);
        Route::get('/borrows/active', [BorrowController::class, 'active']);
        Route::post('/borrows/{txId}/approve', [BorrowController::class, 'approve']);
        Route::post('/borrows/{txId}/reject', [BorrowController::class, 'reject']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{email}', [UserController::class, 'update']);
        Route::delete('/users/{email}', [UserController::class, 'destroy']);
    });
});
