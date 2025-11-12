<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes (no authentication needed)
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::get('/hello', [UserController::class, 'hello']);

// Protected routes (needs JWT token)
Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);

    Route::post('/users', [UserController::class, 'create']); // Create User
    Route::get('/users', [UserController::class, 'index']);   // Get Users
    Route::put('/users/{id}', [UserController::class, 'update']); // Update User
    Route::get('/users/{id}', [UserController::class, 'view']); // View User by ID

    Route::get('/hello', [UserController::class, 'hello']); // Just hello route
});
