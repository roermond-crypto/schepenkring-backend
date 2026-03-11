<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends Model
{
    use HasFactory;

    protected $table = 'faqs'; // Explicitly define the table name

    protected $fillable = [
        'location_id',
        'question',
        'answer',
        'category',
        'source_message_id',
        'trained_by_user_id',
    ];

    // Add default values if needed
    protected $attributes = [
        'category' => 'General',
        'views' => 0,
        'helpful' => 0,
        'not_helpful' => 0
    ];

    protected $casts = [
        'location_id' => 'integer',
        'views' => 'integer',
        'helpful' => 'integer',
        'not_helpful' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trained_by_user_id');
    }
}
