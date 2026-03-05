<?php

namespace App\Actions\Me;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Arr;

class UpdateAddressAction
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(User $user, array $data): User
    {
        $payload = Arr::only($data, [
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
        ]);

        return $this->users->update($user, $payload);
    }
}
