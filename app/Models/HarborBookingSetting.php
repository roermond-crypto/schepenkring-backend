<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarborBookingSetting extends Model
{
    protected $table = 'harbor_booking_settings';

    protected $fillable = [
        'harbor_id',
        'default_duration_minutes',
        'opening_hours_start',
        'opening_hours_end',
        'slot_step_minutes',
        'max_boats_per_timeslot',
        'max_boats_per_day',
        'buffer_minutes',
        'min_booking_hours',
        'max_booking_days',
        'count_pending',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'slot_step_minutes' => 'integer',
        'max_boats_per_timeslot' => 'integer',
        'max_boats_per_day' => 'integer',
        'buffer_minutes' => 'integer',
        'min_booking_hours' => 'integer',
        'max_booking_days' => 'integer',
        'count_pending' => 'boolean',
    ];

    public function harbor()
    {
        return $this->belongsTo(User::class, 'harbor_id');
    }

    public static function defaultAttributes(): array
    {
        return [
            'default_duration_minutes' => 60,
            'opening_hours_start' => '09:00',
            'opening_hours_end' => '17:00',
            'slot_step_minutes' => 15,
            'max_boats_per_timeslot' => 1,
            'max_boats_per_day' => 5,
            'buffer_minutes' => 15,
            'min_booking_hours' => 24,
            'max_booking_days' => 30,
            'count_pending' => true,
        ];
    }
}
