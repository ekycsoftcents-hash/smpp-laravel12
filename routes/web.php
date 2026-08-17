<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/health', [DashboardController::class, 'health'])->name('health');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::get('/providers', [AdminController::class, 'providers'])->name('providers');
    Route::post('/providers', [AdminController::class, 'storeProvider'])->name('providers.store');
    Route::get('/rates', [AdminController::class, 'rates'])->name('rates');
    Route::post('/rates', [AdminController::class, 'storeRate'])->name('rates.store');
    Route::get('/routing', [AdminController::class, 'routing'])->name('routing');
    Route::post('/routing', [AdminController::class, 'storeRouting'])->name('routing.store');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring');
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies');
    Route::post('/currencies', [CurrencyController::class, 'storeCurrency'])->name('currencies.store');
    Route::post('/exchange-rates', [CurrencyController::class, 'storeRate'])->name('exchange-rates.store');
});
