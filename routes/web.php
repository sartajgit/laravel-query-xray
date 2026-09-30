<?php

use Illuminate\Support\Facades\Route;
use Sartajgit\QueryXray\Http\Controllers\DashboardController;

$path = config('query-xray.dashboard.path', 'query-xray');
$middleware = config('query-xray.dashboard.middleware', ['web']);

Route::middleware($middleware)->group(function () use ($path) {
    Route::get($path, [DashboardController::class, 'index'])->name('query-xray.dashboard');
    Route::get($path.'/data', [DashboardController::class, 'data'])->name('query-xray.data');
});