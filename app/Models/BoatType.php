<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BoatType extends Model
{
    protected $fillable = ['name', 'description'];

    public function boatChecks(): BelongsToMany
    {
        return $this->belongsToMany(BoatCheck::class, 'boat_check_boat_type');
    }
}