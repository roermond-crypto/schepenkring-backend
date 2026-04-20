<?php

namespace App\Services;

use App\Models\BuyerProfile;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Arr;

class ProfileSetupService
{
    public function __construct(
        private readonly GooglePlaceLookupService $places,
    ) {
    }

    public function statusFor(User $user): array
    {
        $role = strtolower((string) $user->role);
        $profile = $role === 'seller'
            ? $user->sellerProfile
            : ($role === 'buyer' ? $user->buyerProfile : null);

        $complete = $this->isComplete($user);

        return [
            'role' => $role,
            'selected_role' => $role === 'seller' ? 'seller' : 'buyer',
            'complete' => $complete,
            'next_route' => $complete ? $this->completedRouteFor($user) : '/onboarding',
            'address' => $profile ? [
                'formatted_address' => $profile->formatted_address,
                'street' => $profile->street,
                'house_number' => $profile->house_number,
                'postal_code' => $profile->postal_code,
                'city' => $profile->city,
                'region' => $profile->state,
                'country' => $profile->country,
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
                'place_id' => $profile->place_id,
            ] : null,
        ];
    }

    public function isComplete(User $user): bool
    {
        $role = strtolower((string) $user->role);
        if (!in_array($role, ['seller', 'buyer'], true)) {
            return true;
        }

        $profile = $role === 'seller' ? $user->sellerProfile : $user->buyerProfile;

        // Basic Profile Check
        if (!$profile || !filled($profile->place_id) || !filled($profile->city) || !filled($profile->country)) {
            return false;
        }

        // Onboarding / Verification Check
        if ($role === 'seller') {
            $onboarding = $user->sellerOnboarding;
            return $onboarding !== null && $onboarding->isCurrentlyValid();
        }

        if ($role === 'buyer') {
            $verification = $user->buyerVerification;
            return $verification !== null && $verification->isCurrentlyValid();
        }

        return false;
    }

    public function completedRouteFor(User $user): string
    {
        return strtolower((string) $user->role) === 'seller'
            ? '/dashboard/seller/page' // Adjust based on project structure
            : '/dashboard/buyer/page';
    }

    public function saveAddress(User $user, string $placeId): array
    {
        $details = $this->places->fetchByPlaceId($placeId);
        if (!empty($details['error'])) {
            throw new \RuntimeException((string) $details['error']);
        }

        if (!$this->hasRequiredAddressDetails($details)) {
            throw new \RuntimeException('Please choose a full street-level address from Google Maps, not only a country, region, or sea area.');
        }

        $role = strtolower((string) $user->role);
        if (!in_array($role, ['seller', 'buyer'], true)) {
            throw new \RuntimeException('Profile setup is only available for buyers and sellers.');
        }

        $attributes = [
            'full_name' => $role === 'seller'
                ? ($user->sellerProfile?->full_name ?: $user->name)
                : ($user->buyerProfile?->full_name ?: $user->name),
            'email' => $role === 'seller'
                ? ($user->sellerProfile?->email ?: $user->email)
                : ($user->buyerProfile?->email ?: $user->email),
            'phone' => $role === 'seller'
                ? ($user->sellerProfile?->phone ?: $user->phone)
                : ($user->buyerProfile?->phone ?: $user->phone),
            'formatted_address' => $details['formatted_address'] ?? null,
            'street' => $details['street'] ?? null,
            'house_number' => $details['house_number'] ?? null,
            'address_line_1' => trim(implode(' ', array_filter([
                $details['street'] ?? null,
                $details['house_number'] ?? null,
            ]))) ?: ($details['formatted_address'] ?? null),
            'city' => $details['city'] ?? null,
            'state' => $details['region'] ?? null,
            'postal_code' => $details['postal_code'] ?? null,
            'country' => $details['country_code'] ?? ($details['country'] ?? null),
            'place_id' => $details['place_id'] ?? $placeId,
            'latitude' => $details['latitude'] ?? null,
            'longitude' => $details['longitude'] ?? null,
        ];

        if ($role === 'seller') {
            SellerProfile::query()->updateOrCreate(['user_id' => $user->id], $attributes);
        } else {
            BuyerProfile::query()->updateOrCreate(['user_id' => $user->id], $attributes);
        }

        return $this->statusFor($user->fresh(['sellerProfile', 'buyerProfile']));
    }

    public function search(string $query): array
    {
        return $this->places->searchPredictions($query);
    }

    private function hasRequiredAddressDetails(array $details): bool
    {
        return filled($details['formatted_address'] ?? null)
            && filled($details['city'] ?? null)
            && filled($details['country'] ?? ($details['country_code'] ?? null))
            && (
                filled($details['street'] ?? null)
                || filled($details['postal_code'] ?? null)
            );
    }
}
