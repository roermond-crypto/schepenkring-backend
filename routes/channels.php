<?php

use App\Services\LocationAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('location.{locationId}', function ($user, int $locationId) {
    if ($user->isAdmin()) {
        return true;
    }

    $access = app(LocationAccessService::class);

    return $access->sharesLocation($user, $locationId);
});
