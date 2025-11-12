<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UserController extends Controller
{
    /**
     * Create a new user.
     */
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'name' => 'required|string|min:3|max:50',
            ]);

            $user = User::create([
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'name' => $validated['name'],
            ]);

            Log::info('User created', ['user_id' => $user->id, 'email' => $user->email]);

            // Send confirmation email to user
            try {
                Mail::raw('Your account has been created.', function ($message) use ($user) {
                    $message->to($user->email)->subject('Account Created');
                });
                Log::info('Confirmation email sent', ['user_id' => $user->id]);
            } catch (Exception $e) {
                Log::error('Failed to send confirmation email', ['error' => $e->getMessage()]);
            }

            // Send notification email to admin
            $adminEmail = config('mail.admin_address', 'admin@example.com');
            try {
                Mail::raw("A new user has registered: {$user->email}", function ($message) use ($adminEmail) {
                    $message->to($adminEmail)->subject('New User Registered');
                });
                Log::info('Admin notification email sent', ['admin_email' => $adminEmail]);
            } catch (Exception $e) {
                Log::error('Failed to send admin notification email', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'created_at' => $user->created_at->toIso8601String(),
            ], 201);

        } catch (Exception $e) {
            Log::error('User creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'User creation failed'], 400);
        }
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
            return response()->json(['error' => 'Failed to retrieve user list'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $currentUser = auth()->user();

            if (!$this->canEdit($currentUser, $user)) {
                throw new \Exception('You do not have permission to edit this user.');
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|min:3|max:50',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'role' => 'sometimes|in:Administrator,Manager,User',
                'active' => 'sometimes|boolean',
            ]);

            $user->update($validated);

            Log::info('User updated', ['user_id' => $user->id, 'editor_id' => $currentUser->id]);

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => strtolower($user->role),
                'active' => $user->active,
                'created_at' => $user->created_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('User update failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Determine if the current user can edit the given user.
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
