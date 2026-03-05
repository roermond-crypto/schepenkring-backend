<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoatLike extends Model
{
    protected $fillable = [
        'user_id',
        'yacht_id',
    ];
}
