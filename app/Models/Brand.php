<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * Get the aliases associated with the brand.
     */
    public function aliases()
    {
        // This assumes BrandAlias model exists, if it doesn't wait to find out
        return $this->hasMany(BrandAlias::class);
    }
}
