<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GlossaryTerm extends Model
{
    use HasUuids;

    protected $table = 'glossary_terms';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'term_key',
        'nl',
        'en',
        'de',
        'fr',
        'notes',
    ];
}
