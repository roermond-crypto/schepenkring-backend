<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, Auditable;

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
            'email_verified_at'           => 'datetime',
            'last_login_at'               => 'datetime',
            'password'                    => 'hashed',
            'is_active'                   => 'boolean',
            'lockscreen_timeout'          => 'integer',
            'otp_enabled'                 => 'boolean',
            'type'                        => UserType::class,
            'status'                      => UserStatus::class,
            'date_of_birth'               => 'date',
            'two_factor_enabled'          => 'boolean',
            'two_factor_confirmed_at'     => 'datetime',
            'email_changed_at'            => 'datetime',
            'phone_changed_at'            => 'datetime',
            'password_changed_at'         => 'datetime',
            'notifications_enabled'       => 'boolean',
            'email_notifications_enabled' => 'boolean',
        ];
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isEmployee();
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
        $type = self::normalizeRoleToTypeValue($role);

        if (! $type) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('type', [
            UserType::ADMIN->value,
            UserType::EMPLOYEE->value,
        ]);
    }
    // ── Relations ──────────────────────────────────────────
    public function locations(): BelongsToMany
    {
        return $this->locationRelation();
    }

    public function activeLocations(): BelongsToMany
    {
        $relation = $this->locationRelation();

        if (self::locationUserSupportsActiveFlag()) {
            $relation->wherePivot('active', true);
        }

        return $relation;
    }

    public function clientLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'client_location_id');
    }

    private function locationRelation(): BelongsToMany
    {
        $relation = $this->belongsToMany(Location::class)
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

    public function primaryEmployeeLocation(): ?Location
    {
        if (! $this->isEmployee()) {
            return null;
        }

        if ($this->relationLoaded('locations')) {
            return $this->locations->sortBy('id')->first();
        }

        return $this->locations()->orderBy('locations.id')->first();
    }

    public function resolvedLocation(): ?Location
    {
        if ($this->isClient()) {
            if ($this->relationLoaded('clientLocation')) {
                return $this->clientLocation;
            }

            return $this->clientLocation()->first();
        }

        return $this->primaryEmployeeLocation();
    }

    public function resolvedLocationId(): ?int
    {
        if ($this->isClient()) {
            return $this->client_location_id;
        }

        return $this->primaryEmployeeLocation()?->id;
    }

    public function resolvedLocationRole(): ?string
    {
        if (! $this->isEmployee()) {
            return null;
        }

        return $this->primaryEmployeeLocation()?->pivot?->role;
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'user_notifications')
            ->withPivot('read', 'read_at')
            ->withTimestamps();
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->userNotifications()->unread();
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

    public function hasRole(string $role): bool
    {
        $normalized = self::normalizeRoleName($role);

        return $normalized !== null && $this->role === $normalized;
    }

    public function getLocationIdAttribute(): ?int
    {
        return $this->resolvedLocationId();
    }

    public function getLocationRoleAttribute(): ?string
    {
        return $this->resolvedLocationRole();
    }

    public function getRoleAttribute(): ?string
    {
        $rawRole = $this->attributes['role'] ?? null;
        if (is_string($rawRole) && $rawRole !== '') {
            return strtolower($rawRole);
        }

        $type = $this->type;
        if ($type instanceof UserType) {
            return self::normalizeRoleName($type->value);
        }

        if (is_string($type) && $type !== '') {
            return self::normalizeRoleName($type);
        }

        return null;
    }

    public function setRoleAttribute(?string $value): void
    {
        $type = self::normalizeRoleToTypeValue($value);

        if ($type !== null) {
            $this->attributes['type'] = $type;
        }
    }

    private static function normalizeRoleToTypeValue(?string $role): ?string
    {
        if (! is_string($role) || trim($role) === '') {
            return null;
        }

        return match (strtolower(trim($role))) {
            'admin' => UserType::ADMIN->value,
            'employee' => UserType::EMPLOYEE->value,
            'client' => UserType::CLIENT->value,
            default => in_array(strtoupper($role), array_map(
                static fn (UserType $type) => $type->value,
                UserType::cases()
            ), true) ? strtoupper($role) : null,
        };
    }

    private static function normalizeRoleName(?string $role): ?string
    {
        return match (self::normalizeRoleToTypeValue($role)) {
            UserType::ADMIN->value => 'admin',
            UserType::EMPLOYEE->value => 'employee',
            UserType::CLIENT->value => 'client',
            default => null,
        };
    }
}
