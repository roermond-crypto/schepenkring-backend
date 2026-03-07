<?php

namespace App\Repositories;

use App\Models\Bidder;

class BidderRepository
{
    public function findByEmail(string $email): ?Bidder
    {
        return Bidder::query()->where('email', $email)->first();
    }

    public function findByVerificationTokenHash(string $hash): ?Bidder
    {
        return Bidder::query()->where('verification_token_hash', $hash)->first();
    }

    public function create(array $data): Bidder
    {
        return Bidder::create($data);
    }

    public function update(Bidder $bidder, array $data): Bidder
    {
        $bidder->fill($data);
        $bidder->save();

        return $bidder;
    }
}
