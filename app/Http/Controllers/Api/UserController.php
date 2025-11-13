<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use App\Http\Requests\CreateUserRequest;

class UserController extends Controller
{
    /**
     * Create a new user.
     */
    public function create(CreateUserRequest $request)
    {
        $currentUser = auth()->user();

        if (!$currentUser || !in_array($currentUser->role, ['Administrator', 'Manager'])) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to create users.'
            ], 403);
        }

        // Extra guard (handles race conditions beyond validation)
        $validated = $request->validated();
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'error' => 'email_exists',
                'message' => 'A user with this email already exists.'
            ], 409);
        }

        try {
            $validated = $request->validated();

            $user = User::create([
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'name' => $validated['name'],
            ]);
        } catch (Exception $e) {
            Log::error('User creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'user_creation_failed',
                'message' => 'Failed to create user.',
                'detail' => $e->getMessage()
            ], 500);
        }

        try {
            Mail::raw('Your account has been created.', function ($message) use ($user) {
                $message->to($user->email)->subject('Account Created');
            });
        } catch (Exception $e) {
            Log::error('User welcome email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'welcome_email_failed',
                'message' => 'User created but failed to send welcome email.',
                'user_id' => $user->id
            ], 500);
        }

        try {
            $adminEmail = config('mail.admin_address', 'admin@example.com');
            Mail::raw("A new user has registered: {$user->email}", function ($message) use ($adminEmail) {
                $message->to($adminEmail)->subject('New User Registered');
            });
        } catch (Exception $e) {
            Log::error('Admin notification email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json([
                'error' => 'admin_notification_failed',
                'message' => 'User created, welcome email sent, but admin notification failed.',
                'user_id' => $user->id
            ], 500);
        }

        Log::info('User created', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'created_at' => $user->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Get a paginated list of active users with search, sorting, orders_count, and can_edit.
     */
    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $page = (int) $request->input('page', 1);
            $sortBy = $request->input('sortBy', 'created_at');
            $allowedSorts = ['name', 'email', 'created_at'];
            $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';

            $query = User::where('active', true);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query->orderBy($sortBy)
                ->withCount('orders')
                ->paginate(10, ['*'], 'page', $page);

            $currentUser = auth()->user();

            $usersArray = $users->getCollection()->transform(function ($user) use ($currentUser) {
                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => strtolower($user->role),
                    'created_at' => $user->created_at->toIso8601String(),
                    'orders_count' => $user->orders_count,
                    'can_edit' => $this->canEdit($currentUser, $user),
                ];
            })->toArray();

            Log::info('User list retrieved', ['count' => count($usersArray)]);

            return response()->json([
                'page' => $users->currentPage(),
                'users' => $usersArray
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve user list', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateUserRequest $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $currentUser = auth()->user();

            if (!$this->canEdit($currentUser, $user)) {
                return response()->json([
                    'error' => 'forbidden',
                    'message' => 'You do not have permission to edit this user.'
                ], 403);
            }

            $data = $request->validated();

            try {
                $user->update($data);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    return response()->json([
                        'error' => 'email_exists',
                        'message' => 'A user with this email already exists.'
                    ], 409);
                }
                Log::error('User update query error', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                return response()->json([
                    'error' => 'update_failed',
                    'message' => 'Failed to update user.'
                ], 500);
            }

            Log::info('User updated', ['user_id' => $user->id, 'editor_id' => $currentUser?->id]);

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => strtolower($user->role),
                'active' => $user->active,
                'created_at' => $user->created_at->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found'], 404);
        } catch (Exception $e) {
            Log::error('User update failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'update_failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function view($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => strtolower($user->role),
                'active' => $user->active,
                'created_at' => $user->created_at->toIso8601String(),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve user'], 500);
        }
    }

    public function hello()
    {
        return "Hello, World!";
    }

    /**
     * Determine if the current user can edit the given user.
     * Rules for can_edit:
     * - Administrator: Can edit any user.
     * - Manager: Can only edit users with the role user.
     * - User: Can only edit themselves
     */
    protected function canEdit($currentUser, $user)
    {
        if (!$currentUser) return false;
        if ($currentUser->role === 'Administrator') return true;
        if ($currentUser->role === 'Manager' && $user->role === 'User') return true;
        if ($currentUser->role === 'User' && $currentUser->id === $user->id) return true;
        return false;
    }
}
