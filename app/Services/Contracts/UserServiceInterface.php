<?php

namespace App\Services\Contracts;

use App\Models\User;

interface UserServiceInterface
{
    public function createUser(array $data, ?User $actor): User;
    public function updateUser(int $id, array $data, ?User $actor): User;
    public function canEdit(?User $actor, User $target): bool;
}
