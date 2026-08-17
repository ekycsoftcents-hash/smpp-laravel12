<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/health', [DashboardController::class, 'health'])->name('health');
Route::get('/dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::get('/providers', [AdminController::class, 'providers'])->name('providers');
    Route::post('/providers', [AdminController::class, 'storeProvider'])->name('providers.store');
    Route::put('/providers/{provider}', [AdminController::class, 'updateProvider'])->name('providers.update');
    Route::delete('/providers/{provider}', [AdminController::class, 'destroyProvider'])->name('providers.destroy');
    Route::get('/rates', [AdminController::class, 'rates'])->name('rates');
    Route::post('/rates', [AdminController::class, 'storeRate'])->name('rates.store');
    Route::post('/rates/import', [AdminController::class, 'importRates'])->name('rates.import');
    Route::get('/rates/template', [AdminController::class, 'downloadRateTemplate'])->name('rates.template');
    Route::put('/rates/{rate}', [AdminController::class, 'updateRate'])->name('rates.update');
    Route::delete('/rates/{rate}', [AdminController::class, 'destroyRate'])->name('rates.destroy');
    Route::get('/routing', [AdminController::class, 'routing'])->name('routing');
    Route::put('/routing/{rule}', [AdminController::class, 'updateRouting'])->name('routing.update');
    Route::delete('/routing/{rule}', [AdminController::class, 'destroyRouting'])->name('routing.destroy');
    Route::post('/routing', [AdminController::class, 'storeRouting'])->name('routing.store');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [AdminController::class, 'exportReports'])->name('reports.export');
    Route::get('/reports/providers/export', [AdminController::class, 'exportProviderReports'])->name('reports.providers.export');
    Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring');
    Route::get('/monitoring/live', [MonitoringController::class, 'live'])->name('monitoring.live');
    Route::get('/monitoring/providers/live', [MonitoringController::class, 'providerLive'])->name('monitoring.providers.live');
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies');
    Route::post('/currencies', [CurrencyController::class, 'storeCurrency'])->name('currencies.store');
    Route::put('/currencies/{currency}', [CurrencyController::class, 'updateCurrency'])->name('currencies.update');
    Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroyCurrency'])->name('currencies.destroy');
    Route::post('/exchange-rates', [CurrencyController::class, 'storeRate'])->name('exchange-rates.store');
    Route::put('/exchange-rates/{rate}', [CurrencyController::class, 'updateRate'])->name('exchange-rates.update');
    Route::delete('/exchange-rates/{rate}', [CurrencyController::class, 'destroyRate'])->name('exchange-rates.destroy');
});
