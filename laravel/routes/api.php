<?php
use Illuminate\Support\Facades\Route;
Route::post('/payments/telebirr/callback',\App\Http\Controllers\TelebirrWebhookController::class)->middleware('throttle:120,1')->name('telebirr.callback');
