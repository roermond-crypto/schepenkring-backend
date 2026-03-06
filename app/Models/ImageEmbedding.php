<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageEmbedding extends Model
{
    protected $fillable = [
        'yacht_id',
        'is_main_image',
        'filename',
        'public_url',
        'embedding',
        'description'
    ];

    protected $casts = [
        'embedding' => 'array',
        'is_main_image' => 'boolean',
    ];

    /**
     * The yacht this embedding belongs to.
     */
    public function yacht()
    {
        return $this->belongsTo(\App\Models\Yacht::class);
    }
}