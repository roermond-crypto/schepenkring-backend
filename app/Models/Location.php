<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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
        return $this->userRelation();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(User::class, 'client_location_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->userRelation()
            ->where('users.type', UserType::EMPLOYEE->value);
    }

    public function activeEmployees(): BelongsToMany
    {
        $relation = $this->userRelation()
            ->where('users.type', UserType::EMPLOYEE->value);

        if (self::locationUserSupportsActiveFlag()) {
            $relation->wherePivot('active', true);
        }

        return $relation;
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

    private function userRelation(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();

        if (self::locationUserSupportsActiveFlag()) {
            $relation->withPivot('active');
        }

        return $relation;
    }

    private static function locationUserSupportsActiveFlag(): bool
    {
        try {
            return Schema::hasColumn('location_user', 'active');
        } catch (\Throwable) {
            return false;
        }
    }
}
