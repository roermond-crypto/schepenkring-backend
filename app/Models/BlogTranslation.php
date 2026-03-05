<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTranslation extends Model
{
    protected $fillable = [
        'blog_id',
        'locale',
        'title',
        'excerpt',
        'content',
        'meta_title',
        'meta_description',
        'status',
        'source_hash',
        'translated_from_hash',
        'is_legal',
    ];

    protected $casts = [
        'is_legal' => 'boolean',
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
}
