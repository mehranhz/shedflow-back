<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/login', [AuthController::class, 'loginWithPassword']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/request-otp', [AuthController::class, 'loginWithOTP']);
Route::post('/verify-otp', [AuthController::class, 'verifyOTPForUsername']);
