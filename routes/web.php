<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RandomChatController;

// Existing pages preserved.
Route::view('/', 'welcome')->name('home');
Route::view('/dashboard', 'dashboard')->name('dashboard');
Route::view('/screenshare', 'screenshare')->name('screenshare');

// WebRTC random video chat. Web middleware provides cookie session + CSRF.
Route::get('/random', [RandomChatController::class, 'page'])->name('rtc.page');
Route::prefix('random/api')->group(function () {
    Route::post('/join', [RandomChatController::class, 'join'])->middleware('throttle:rtc.join')->name('rtc.join');
    Route::post('/state', [RandomChatController::class, 'state'])->middleware('throttle:rtc.state')->name('rtc.state');
    Route::post('/signal', [RandomChatController::class, 'signal'])->middleware('throttle:rtc.signal')->name('rtc.signal');
    Route::post('/leave', [RandomChatController::class, 'leave'])->middleware('throttle:rtc.action')->name('rtc.leave');
    Route::post('/report', [RandomChatController::class, 'report'])->middleware('throttle:rtc.report')->name('rtc.report');
});
