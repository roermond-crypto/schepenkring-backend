<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FaqEntry extends Model
{
    use HasUuids;

    protected $table = 'faq';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'category',
        'subcategory',
        'namespace',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function translations()
    {
        return $this->hasMany(FaqTranslation::class, 'faq_id');
    }
}
