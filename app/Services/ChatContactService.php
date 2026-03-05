<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;

class ChatContactService
{
    public function resolveContact(?array $payload, ?User $user): ?Contact
    {
        $payload = $payload ?: [];

        $email = $payload['email'] ?? ($user?->email);
        $phone = $payload['phone'] ?? null;
        $whatsappId = $payload['whatsapp_user_id'] ?? null;

        $query = Contact::query();
        if ($email) {
            $query->orWhere('email', $email);
        }
        if ($phone) {
            $query->orWhere('phone', $phone);
        }
        if ($whatsappId) {
            $query->orWhere('whatsapp_user_id', $whatsappId);
        }

        $contact = $query->first();

        $attributes = [
            'user_id' => $user?->id,
            'name' => $payload['name'] ?? ($user?->name),
            'email' => $email,
            'phone' => $phone,
            'whatsapp_user_id' => $whatsappId,
            'language_preferred' => $payload['language_preferred'] ?? null,
            'do_not_contact' => (bool) ($payload['do_not_contact'] ?? false),
            'consent_marketing' => (bool) ($payload['consent_marketing'] ?? false),
            'consent_service_messages' => (bool) ($payload['consent_service_messages'] ?? true),
        ];

        if (!$contact) {
            if (!$email && !$phone && !$whatsappId) {
                return null;
            }

            return Contact::create($attributes);
        }

        $contact->fill(array_filter($attributes, fn ($value) => $value !== null));
        $contact->save();

        return $contact;
    }
}
