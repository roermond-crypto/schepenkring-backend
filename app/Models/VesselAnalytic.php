<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VesselAnalytic extends Model
{
    protected $fillable = [
    'external_id', 'name', 'model', 'price', 
    'ref_code', 'url', 'ip_address', 'user_agent', 'raw_specs'
];

protected $casts = [
    'raw_specs' => 'array'
];
}
