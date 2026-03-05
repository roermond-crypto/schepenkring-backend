<?php

namespace App\Actions\Me;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Arr;

class UpdateProfileAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(User $user, array $data): User
    {
        $payload = Arr::only($data, ['name', 'timezone', 'locale']);

        return $this->users->update($user, $payload);
    }
}
