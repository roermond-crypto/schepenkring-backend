<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $table = 'faqs'; // Explicitly define the table name

    protected $fillable = [
        'question',
        'answer',
        'category'
    ];

    // Add default values if needed
    protected $attributes = [
        'category' => 'General',
        'views' => 0,
        'helpful' => 0,
        'not_helpful' => 0
    ];

    protected $casts = [
        'views' => 'integer',
        'helpful' => 'integer',
        'not_helpful' => 'integer'
    ];
}