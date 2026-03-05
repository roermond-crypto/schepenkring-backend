<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'preferred_locale',
        'role',
        'status',
        'access_level',
        'registration_ip',
        'user_agent',
        'terms_accepted_at',
        'profile_image',
        'phone_number',
        'address',
        'city',
        'state',
        'postcode',
        'country',
        'partner_id',
        // new fields
        'relationNumber',
        'firstName',
        'lastName',
        'prefix',
        'initials',
        'title',
        'salutation',
        'attentionOf',
        'identification',
        'dateOfBirth',
        'website',
        'mobile',
        'street',
        'houseNumber',
        'note',
        'claimHistoryCount',
        'lockscreen_code',
        'lockscreen_timeout',
        'otp_enabled',
        'notifications_enabled',
        'email_notifications_enabled',
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
    'dateOfBirth' => 'date',
    'claimHistoryCount' => 'integer',
    'notifications_enabled' => 'boolean',
    'email_notifications_enabled' => 'boolean',
    'email_verification_expires_at' => 'datetime',
        ];
    }

    // Add this relationship to your existing User model
    public function pagePermissions()
    {
        return $this->hasMany(UserPagePermission::class);
    }

    // Add this method to get permission value for a page
    public function getPagePermission($pageKey)
    {
        $permission = $this->pagePermissions()
            ->whereHas('page', function ($query) use ($pageKey) {
                $query->where('page_key', $pageKey);
            })
            ->first();

        return $permission ? $permission->permission_value : 0;
    }



    // NEW: Activity logs relationship
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // NEW: Notifications relationship
    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'user_notifications')
            ->withPivot('read', 'read_at')
            ->withTimestamps();
    }

    // NEW: Get unread notifications
    public function unreadNotifications()
    {
        return $this->notifications()->wherePivot('read', false);
    }

    // NEW: Get unread notifications count
    public function getUnreadNotificationsCountAttribute()
    {
        return $this->unreadNotifications()->count();
    }

    public function likedYachts()
    {
        return $this->belongsToMany(Yacht::class, 'boat_likes')->withTimestamps();
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function partnerProfile()
    {
        return $this->hasOne(PartnerProfile::class);
    }



    protected static function booted()
    {
        static::creating(function ($user) {
            // Automatically assign token if role is Partner
            if ($user->role === 'Partner' && empty($user->partner_token)) {
                $user->partner_token = self::generateUniquePartnerToken();
            }
        });

        static::updating(function ($user) {
            // If role changes to Partner and no token exists, generate one
            if ($user->isDirty('role') && $user->role === 'Partner' && empty($user->partner_token)) {
                $user->partner_token = self::generateUniquePartnerToken();
            }
        });
    }

    private static function generateUniquePartnerToken(): string
    {
        do {
            $token = Str::random(32);
        } while (User::where('partner_token', $token)->exists());

        return $token;
    }


    // Relationship: the partner who owns this user
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    // Relationship: users owned by this partner
    public function ownedUsers()
    {
        return $this->hasMany(User::class, 'partner_id');
    }

    public function isAdmin()
    {
        return $this->role === 'Admin';
    }
}
