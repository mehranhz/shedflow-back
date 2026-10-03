<?php

use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\InvitationController;
use App\Http\Controllers\v1\OrganizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix("/v1")->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');


    Route::post('/login', [AuthController::class, 'loginWithPassword']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/request-otp', [AuthController::class, 'loginWithOTP']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOTPForUsername']);


    Route::prefix('/organization')->group(function (){
        Route::get('/', [OrganizationController::class, 'index']);
        Route::post('/', [OrganizationController::class, 'store']);
    })->withoutMiddleware(['auth:sanctum']);

    Route::prefix("/invitation")->group(function (){
        Route::post('/', [InvitationController::class, 'store']);
    });

});
