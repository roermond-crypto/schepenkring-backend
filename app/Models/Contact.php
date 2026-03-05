<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'whatsapp_user_id',
        'language_preferred',
        'do_not_contact',
        'consent_marketing',
        'consent_service_messages',
    ];

    protected $casts = [
        'do_not_contact' => 'boolean',
        'consent_marketing' => 'boolean',
        'consent_service_messages' => 'boolean',
    ];

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'contact_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
