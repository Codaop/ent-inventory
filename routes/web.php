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

Route::get('/debug/reports', function (\App\Repositories\TransactionSheetRepository $txs, \Illuminate\Http\Request $request) {
    $all = $txs->allTransactions();
    $from = (string) $request->query('fromDate', '');
    $to = (string) $request->query('toDate', '');
    $type = (string) $request->query('type', '');

    $filtered = array_filter($all, function (array $tx) use ($from, $to, $type): bool {
        if ($type !== '' && $tx['type'] !== $type) { return false; }
        if ($from !== '' && $tx['timestamp'] !== '') {
            if (substr($tx['timestamp'], 0, 10) < $from) { return false; }
        }
        if ($to !== '' && $tx['timestamp'] !== '') {
            if (substr($tx['timestamp'], 0, 10) > $to) { return false; }
        }
        return true;
    });

    return response()->json([
        'total_rows' => count($all),
        'filtered_rows' => count($filtered),
        'types' => array_count_values(array_map(fn ($t) => (string) $t['type'], $all)),
        'statuses' => array_count_values(array_map(fn ($t) => (string) $t['status'], $all)),
        'timestamp_min' => min(array_map(fn ($t) => (string) $t['timestamp'], $all) ?: ['']),
        'timestamp_max' => max(array_map(fn ($t) => (string) $t['timestamp'], $all) ?: ['']),
    ]);
});

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
    Route::get('/borrows/reports', [BorrowController::class, 'reports']);
    Route::get('/transactions/recent', [BorrowController::class, 'recent']);
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
