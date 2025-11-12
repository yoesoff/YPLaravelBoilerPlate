<?php
use App\Http\Controllers\Api\UserController;

Route::post('/users', [UserController::class, 'create']); // Create User
Route::get('/users', [UserController::class, 'index']);   // Get Users
