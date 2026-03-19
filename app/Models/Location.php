<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
        'chat_widget_enabled',
        'chat_widget_welcome_text',
        'chat_widget_theme',
    ];

    protected $casts = [
        'chat_widget_enabled' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'active')
            ->withTimestamps();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(User::class, 'client_location_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'active')
            ->withTimestamps()
            ->where('users.type', UserType::EMPLOYEE->value);
    }

    public function activeEmployees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'active')
            ->withTimestamps()
            ->where('users.type', UserType::EMPLOYEE->value)
            ->wherePivot('active', true);
    }

    public function yachts(): HasMany
    {
        return $this->hasMany(Yacht::class);
    }

    public function knowledgeQuestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainQuestion::class);
    }

    public function knowledgeSuggestions(): HasMany
    {
        return $this->hasMany(KnowledgeBrainSuggestion::class);
    }
}
