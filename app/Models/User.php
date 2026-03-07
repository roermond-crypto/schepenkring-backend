<?php

namespace App\Models;

use App\Traits\Auditable;
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
    use HasFactory, Notifiable, HasApiTokens, Auditable;

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
        'role',
        'phone',
        'avatar',
        'is_active',
        'invited_by',
        'last_login_at',
        'lockscreen_code',
        'lockscreen_timeout',
        'otp_enabled',
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

    protected $hidden = [
        'password',
        'remember_token',
        'otp_secret',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at'      => 'datetime',
            'password'           => 'hashed',
            'is_active'          => 'boolean',
            'lockscreen_timeout' => 'integer',
            'otp_enabled'        => 'boolean',
            'email_verified_at' => 'datetime',
            'type' => UserType::class,
            'status' => UserStatus::class,
            'date_of_birth' => 'date',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'email_changed_at' => 'datetime',
            'phone_changed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'notifications_enabled' => 'boolean',
            'email_notifications_enabled' => 'boolean',
        ];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'employee']);
    }

    // ── Relationships ────────────────────────────────────

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitees()
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function yachts()
    {
        return $this->hasMany(Yacht::class);
    }

    // ── Scopes ───────────────────────────────────────────

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', ['admin', 'employee']);
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
