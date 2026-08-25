<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTimeOff extends Model
{
    protected $fillable = [
        'staff_id',
        'start_date',
        'end_date',
        'reason',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function coversDate($date): bool
    {
        $date = \Illuminate\Support\Carbon::parse($date)->startOfDay();

        return $this->start_date->lte($date) && $this->end_date->gte($date);
    }
}
