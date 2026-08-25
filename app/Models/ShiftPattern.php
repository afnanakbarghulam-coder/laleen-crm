<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftPattern extends Model
{
    protected $fillable = [
        'staff_id',
        'repeat_frequency',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function blocks()
    {
        return $this->hasMany(ShiftBlock::class);
    }

    public function coversDate($date): bool
    {
        $date = \Illuminate\Support\Carbon::parse($date)->startOfDay();

        if ($this->start_date->gt($date)) {
            return false;
        }

        return !$this->end_date || $this->end_date->gte($date);
    }
}
