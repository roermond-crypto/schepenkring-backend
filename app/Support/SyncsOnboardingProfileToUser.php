<?php

namespace App\Support;

use App\Models\User;

/**
 * Seller/buyer onboarding profile forms collect fields (full_name, phone,
 * birth_date, address) that overlap with the main users table, but
 * historically only ever wrote to the separate seller_profiles /
 * buyer_profiles tables — so the account page (which reads straight off
 * users) showed those fields as empty even after a user completed
 * onboarding. This syncs the overlapping subset onto the User record.
 *
 * Only fills currently-empty user fields, so it never clobbers values the
 * user has since edited directly on their account page.
 */
trait SyncsOnboardingProfileToUser
{
    private function syncOnboardingProfileToUser(User $user, array $payload): void
    {
        $updates = [];

        if (blank($user->first_name) && blank($user->last_name) && filled($payload['full_name'] ?? null)) {
            [$firstName, $lastName] = $this->splitFullName($payload['full_name']);
            if (filled($firstName)) {
                $updates['first_name'] = $firstName;
            }
            if (filled($lastName)) {
                $updates['last_name'] = $lastName;
            }
        }

        if (blank($user->phone) && filled($payload['phone'] ?? null)) {
            $updates['phone'] = $payload['phone'];
        }

        if (blank($user->date_of_birth) && filled($payload['birth_date'] ?? null)) {
            $updates['date_of_birth'] = $payload['birth_date'];
        }

        if (blank($user->address_line1) && blank($user->street) && filled($payload['address_line_1'] ?? null)) {
            $updates['address_line1'] = $payload['address_line_1'];
        }

        if (blank($user->address_line2) && filled($payload['address_line_2'] ?? null)) {
            $updates['address_line2'] = $payload['address_line_2'];
        }

        if (blank($user->city) && filled($payload['city'] ?? null)) {
            $updates['city'] = $payload['city'];
        }

        if (blank($user->state) && filled($payload['state'] ?? null)) {
            $updates['state'] = $payload['state'];
        }

        if (blank($user->postal_code) && filled($payload['postal_code'] ?? null)) {
            $updates['postal_code'] = $payload['postal_code'];
        }

        if (blank($user->country) && filled($payload['country'] ?? null)) {
            $updates['country'] = $payload['country'];
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
