<?php

use Illuminate\Support\Facades\Route;
use Sartajgit\QueryXray\Http\Controllers\DashboardController;

$path = config('query-xray.dashboard.path', 'query-xray');
$middleware = config('query-xray.dashboard.middleware', ['web']);

Route::middleware($middleware)->group(function () use ($path) {
    Route::get($path, [DashboardController::class, 'index'])->name('query-xray.dashboard');
    Route::get($path.'/data', [DashboardController::class, 'data'])->name('query-xray.data');
    Route::delete($path.'/clear', [DashboardController::class, 'clear'])->name('query-xray.clear');
    Route::delete($path.'/clear/{type}', [DashboardController::class, 'clearType'])->name('query-xray.clear-type');
});