<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenMarineFieldMapping extends Model
{
    protected $fillable = [
        'schepenkring_field',
        'openmarine_xml_path',
        'group_label',
        'is_required',
        'notes',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];
}
