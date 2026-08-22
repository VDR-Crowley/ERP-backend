<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

Route::post('/password/forgot', [PasswordResetController::class, 'requestCode'])->middleware('throttle:password-reset');
Route::post('/password/verify-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:password-reset');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');

// Só aceita token com ability `refresh` — rotaciona o par (access+refresh).
Route::post('/refresh', [RefreshTokenController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:refresh']);

// Rotas normais da API: exigem token com ability `access` (um refresh token
// vazando não serve pra nada além de chamar /refresh).
Route::middleware(['auth:sanctum', 'abilities:access'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/logout', [LogoutController::class, 'store']);
});
