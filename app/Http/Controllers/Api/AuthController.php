<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user and return JWT token
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|in:Administrator,Manager,User',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'User',
            'active' => true,
        ]);

        $token = JWTAuth::fromUser($user);

        Log::info('User registered', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => strtolower($user->role),
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and return JWT token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            Log::warning('Failed login attempt', ['email' => $request->email]);
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = auth()->user();

        if (!$user->active) {
            auth()->logout();
            return response()->json(['error' => 'Account is inactive'], 403);
        }

        Log::info('User logged in', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => strtolower($user->role),
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout user and invalidate token
     */
    public function logout()
    {
        try {
            $user = auth()->user();
            auth()->logout();

            Log::info('User logged out', ['user_id' => $user->id]);

            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Logout failed'], 500);
        }
    }

    /**
     * Refresh JWT token
     */
    public function refresh()
    {
        try {
            $newToken = auth()->refresh();

            return response()->json([
                'message' => 'Token refreshed successfully',
                'token' => $newToken,
            ]);
        } catch (\Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Token refresh failed'], 401);
        }
    }

    /**
     * Get authenticated user info
     */
    public function me()
    {
        try {
            $user = auth()->user();

            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => strtolower($user->role),
                'active' => $user->active,
                'created_at' => $user->created_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user info', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
