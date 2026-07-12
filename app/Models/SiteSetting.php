<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'footer_tagline',
        'contact_email',
        'contact_phone',
        'contact_address',
        'social_links',
    ];

    protected $casts = [
        'footer_tagline' => 'array',
        'social_links' => 'array',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
