<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'password',
        'type',
        'status',
        'client_location_id',
        'timezone',
        'locale',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'otp_secret',
        'email_changed_at',
        'phone_changed_at',
        'password_changed_at',
        'last_login_at',
        'notifications_enabled',
        'email_notifications_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'status' => UserStatus::class,
            'date_of_birth' => 'date',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'email_changed_at' => 'datetime',
            'phone_changed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notifications_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
        ];
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function clientLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'client_location_id');
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'user_notifications')
            ->withPivot('read', 'read_at')
            ->withTimestamps();
    }

    public function unreadNotifications()
    {
        return $this->notifications()->wherePivot('read', false);
    }

    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->unreadNotifications()->count();
    }

    public function isAdmin(): bool
    {
        return $this->type === UserType::ADMIN;
    }

    public function isEmployee(): bool
    {
        return $this->type === UserType::EMPLOYEE;
    }

    public function isClient(): bool
    {
        return $this->type === UserType::CLIENT;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
