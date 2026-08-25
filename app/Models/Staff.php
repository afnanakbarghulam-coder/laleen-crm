<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birthday',
        'address_line1',
        'city',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'branch',
        'skills',
        'working_hours',
        'weekly_off',
        'availability_status',
        'off_from',
        'off_to',
        'profile_picture',
        'bookable',
        'start_date',
        'end_date',
        'employment_type',
        'staff_member_id',
        'internal_notes',
        'hourly_wage',
        'commission_rate',
        'user_id',
    ];

    protected $casts = [
        'skills' => 'array',
        'working_hours' => 'array',
        'weekly_off' => 'array',
        'bookable' => 'boolean',
        'birthday' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function blocks()
    {
        return $this->hasMany(StaffBlock::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shiftPatterns()
    {
        return $this->hasMany(ShiftPattern::class);
    }

    public function timeOffs()
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    public function activePatternFor($date): ?ShiftPattern
    {
        return $this->shiftPatterns
            ->filter(fn ($pattern) => $pattern->coversDate($date))
            ->sortByDesc('start_date')
            ->first();
    }
}
