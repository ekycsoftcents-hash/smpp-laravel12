<?php
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/health', [DashboardController::class, 'health'])->name('health');
