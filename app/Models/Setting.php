<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'description'];

    protected $casts = [];

    // ── Accessors ────────────────────────────────────────

    /**
     * Get the typed value.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    // ── Static helpers ───────────────────────────────────

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->typed_value : $default;
    }

    /**
     * Set a setting value (upsert).
     */
    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string', ?string $description = null): static
    {
        $storeValue = is_array($value) ? json_encode($value) : (string) $value;
        if (is_array($value)) $type = 'json';

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value'       => $storeValue,
                'group'       => $group,
                'type'        => $type,
                'description' => $description,
            ],
        );
    }

    /**
     * Get all settings for a group.
     */
    public static function forGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn($s) => [$s->key => $s->typed_value])
            ->toArray();
    }
}
