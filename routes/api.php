<?php

use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/sms/send', [SmsController::class, 'send']);
    Route::get('/sms/{message_id}', [SmsController::class, 'status']);
    Route::get('/sms', fn () => response()->json(['data' => [], 'meta' => ['total' => 0]]));
    Route::get('/balance', fn () => response()->json(['currency' => config('smpp.currency'), 'balance' => '0.00']));
    Route::get('/rates', fn () => response()->json(['data' => []]));
    Route::get('/dlr', fn () => response()->json(['data' => []]));
});

Route::post('/webhooks/jasmin/dlr', [SmsController::class, 'jasminDlr']);
