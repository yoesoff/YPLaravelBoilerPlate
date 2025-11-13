<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Services\Contracts\UserServiceInterface;
use App\Exceptions\Domain\ForbiddenOperationException;
use App\Exceptions\Domain\DuplicateEmailException;
use App\Exceptions\Domain\EmailDispatchException;
use App\Exceptions\Domain\EntityNotFoundException;

class UserService implements UserServiceInterface
{
    public function createUser(array $data, ?User $actor): User
    {
        if (!$actor || !in_array($actor->role, ['Administrator', 'Manager'])) {
            throw new ForbiddenOperationException('Not allowed to create users.');
        }

        try {
            $user = User::create([
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'name' => $data['name'],
                'role' => $data['role'] ?? 'User',
                'active' => $data['active'] ?? true,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new DuplicateEmailException('Email already exists.');
            }
            throw $e;
        }

        $this->sendWelcomeEmail($user);
        $this->notifyAdmin($user);

        Log::info('UserService.createUser success', ['user_id' => $user->id]);
        return $user;
    }

    public function updateUser(int $id, array $data, ?User $actor): User
    {
        $user = User::find($id);
        if (!$user) {
            throw new EntityNotFoundException('User not found.');
        }

        if (!$this->canEdit($actor, $user)) {
            throw new ForbiddenOperationException('Not allowed to edit this user.');
        }

        try {
            $user->update($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new DuplicateEmailException('Email already exists.');
            }
            throw $e;
        }

        Log::info('UserService.updateUser success', ['user_id' => $user->id, 'actor_id' => $actor?->id]);
        return $user;
    }

    public function canEdit(?User $actor, User $target): bool
    {
        if (!$actor) return false;
        if ($actor->role === 'Administrator') return true;
        if ($actor->role === 'Manager' && $target->role === 'User') return true;
        if ($actor->role === 'User' && $actor->id === $target->id) return true;
        return false;
    }

    protected function sendWelcomeEmail(User $user): void
    {
        try {
            Mail::raw('Your account has been created.', function ($m) use ($user) {
                $m->to($user->email)->subject('Account Created');
            });
        } catch (\Throwable $e) {
            Log::warning('Welcome email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw new EmailDispatchException('welcome_email', $e->getMessage());
        }
    }

    protected function notifyAdmin(User $user): void
    {
        $adminEmail = config('mail.admin_address', 'admin@example.com');
        try {
            Mail::raw("A new user has registered: {$user->email}", function ($m) use ($adminEmail) {
                $m->to($adminEmail)->subject('New User Registered');
            });
        } catch (\Throwable $e) {
            Log::warning('Admin notification email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            // Non-fatal: choose to not throw; or throw new EmailDispatchException('admin_notification', $e->getMessage());
        }
    }
}
