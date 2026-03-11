<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Support\CopilotLanguage;
use Illuminate\Validation\ValidationException;

class ChatContactService
{
    public function __construct(
        private CopilotLanguage $language
    ) {
    }

    public function resolveContact(?array $payload, ?User $user): ?Contact
    {
        $payload = $payload ?: [];

        $email = $payload['email'] ?? ($user?->email);
        $phone = $payload['phone'] ?? null;
        $whatsappId = $payload['whatsapp_user_id'] ?? null;

        $contact = null;
        if ($email || $phone || $whatsappId) {
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
        }

        $attributes = [
            'user_id' => $user?->id,
            'name' => $payload['name'] ?? ($user?->name),
            'email' => $email,
            'phone' => $phone,
            'whatsapp_user_id' => $whatsappId,
            'language_preferred' => $this->language->normalize($payload['language_preferred'] ?? null),
            'do_not_contact' => (bool) ($payload['do_not_contact'] ?? false),
            'consent_marketing' => (bool) ($payload['consent_marketing'] ?? false),
            'consent_service_messages' => (bool) ($payload['consent_service_messages'] ?? true),
        ];

        if (!$contact) {
            if (!$email && !$phone && !$whatsappId && empty($attributes['name'])) {
                return null;
            }

            return Contact::create($attributes);
        }

        $contact->fill(array_filter($attributes, fn ($value) => $value !== null));
        $contact->save();

        return $contact;
    }

    public function updateConversationContact(Conversation $conversation, array $payload): Contact
    {
        $payload = $payload ?: [];
        $contact = $conversation->contact;
        $attributes = $this->normalizeContactAttributes($payload);
        $linkedUserId = $conversation->user_id ?? $contact?->user_id;

        if ($contact) {
            $this->ensureUniqueIdentifiersAvailable($attributes, $contact->id);
        } else {
            $contact = $this->findExistingContact($attributes);

            if (! $contact) {
                if (! $this->hasProvidedFields($attributes)) {
                    throw ValidationException::withMessages([
                        'contact' => ['At least one contact field is required.'],
                    ]);
                }

                $contact = new Contact();
            }
        }

        if ($linkedUserId && ! $contact->user_id) {
            $contact->user_id = $linkedUserId;
        }

        foreach ($attributes as $field => $value) {
            $contact->{$field} = $value;
        }

        $contact->save();

        if ($conversation->contact_id !== $contact->id) {
            $conversation->contact_id = $contact->id;
        }

        if (! $conversation->user_id && $contact->user_id) {
            $conversation->user_id = $contact->user_id;
        }

        if (array_key_exists('language_preferred', $attributes)) {
            $conversation->language_preferred = $attributes['language_preferred'];
        }

        $conversation->save();

        return $contact;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeContactAttributes(array $payload): array
    {
        $attributes = [];

        foreach (['name', 'email', 'phone', 'whatsapp_user_id'] as $field) {
            if (array_key_exists($field, $payload)) {
                $attributes[$field] = $this->normalizeNullableString($payload[$field]);
            }
        }

        if (array_key_exists('language_preferred', $payload)) {
            $attributes['language_preferred'] = $this->language->normalize($payload['language_preferred']);
        }

        foreach (['do_not_contact', 'consent_marketing', 'consent_service_messages'] as $field) {
            if (array_key_exists($field, $payload)) {
                $attributes[$field] = (bool) $payload[$field];
            }
        }

        return $attributes;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureUniqueIdentifiersAvailable(array $attributes, string $contactId): void
    {
        foreach (['email', 'phone', 'whatsapp_user_id'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $value = $attributes[$field];
            if ($value === null) {
                continue;
            }

            $exists = Contact::query()
                ->where($field, $value)
                ->where('id', '!=', $contactId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    $field => [sprintf('The %s is already linked to another contact.', str_replace('_', ' ', $field))],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function findExistingContact(array $attributes): ?Contact
    {
        foreach (['email', 'phone', 'whatsapp_user_id'] as $field) {
            $value = $attributes[$field] ?? null;
            if ($value === null) {
                continue;
            }

            $contact = Contact::query()->where($field, $value)->first();
            if ($contact) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasProvidedFields(array $attributes): bool
    {
        return $attributes !== [];
    }
}
