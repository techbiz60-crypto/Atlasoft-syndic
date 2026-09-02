<?php

use App\Http\Controllers\Api\EmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
