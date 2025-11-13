<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\Contracts\UserServiceInterface;
use App\Exceptions\Domain\ForbiddenOperationException;
use App\Exceptions\Domain\DuplicateEmailException;
use App\Exceptions\Domain\EmailDispatchException;
use App\Exceptions\Domain\EntityNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Database\QueryException;

class UserController extends Controller
{
    public function __construct(private UserServiceInterface $users) {}

    public function create(CreateUserRequest $request)
    {
        try {
            $user = $this->users->createUser($request->validated(), auth()->user());
            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => strtolower($user->role),
                'active' => $user->active,
                'created_at' => $user->created_at->toIso8601String(),
            ], 201);
        } catch (ForbiddenOperationException $e) {
            return response()->json(['error' => 'forbidden', 'message' => $e->getMessage()], 403);
        } catch (DuplicateEmailException $e) {
            return response()->json(['error' => 'email_exists', 'message' => $e->getMessage()], 409);
        } catch (EmailDispatchException $e) {
            return response()->json(['error' => 'email_failed', 'message' => $e->getMessage()], 500);
        } catch (QueryException $e) {
            Log::error('Create user DB error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'user_creation_failed'], 500);
        } catch (\Throwable $e) {
            Log::error('Create user unexpected', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'user_creation_failed'], 500);
        }
    }

    public function update(UpdateUserRequest $request, $id)
    {
        try {
            $user = $this->users->updateUser((int)$id, $request->validated(), auth()->user());
            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => strtolower($user->role),
                'active' => $user->active,
                'created_at' => $user->created_at->toIso8601String(),
            ]);
        } catch (EntityNotFoundException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        } catch (ForbiddenOperationException $e) {
            return response()->json(['error' => 'forbidden', 'message' => $e->getMessage()], 403);
        } catch (DuplicateEmailException $e) {
            return response()->json(['error' => 'email_exists', 'message' => $e->getMessage()], 409);
        } catch (QueryException $e) {
            Log::error('Update user DB error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'update_failed'], 500);
        } catch (\Throwable $e) {
            Log::error('Update user unexpected', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'update_failed'], 500);
        }
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $page = (int)$request->input('page', 1);
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

        $users = $query->orderBy($sortBy)->withCount('orders')->paginate(10, ['*'], 'page', $page);
        $actor = auth()->user();

        $items = $users->getCollection()->map(function ($u) use ($actor) {
            return [
                'id' => $u->id,
                'email' => $u->email,
                'name' => $u->name,
                'role' => strtolower($u->role),
                'created_at' => $u->created_at->toIso8601String(),
                'orders_count' => $u->orders_count,
                'can_edit' => $this->users->canEdit($actor, $u),
            ];
        })->values();

        return response()->json([
            'page' => $users->currentPage(),
            'users' => $items
        ]);
    }

    public function view($id)
    {
        $user = User::find($id);
        if (!$user) return response()->json(['error' => 'not_found'], 404);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => strtolower($user->role),
            'active' => $user->active,
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }

    public function hello()
    {
        return 'Hello, World!';
    }
}
