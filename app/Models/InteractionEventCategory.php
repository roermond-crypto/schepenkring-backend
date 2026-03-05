<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionEventCategory extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function eventTypes()
    {
        return $this->hasMany(InteractionEventType::class, 'category_id');
    }
}
