<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftBlock extends Model
{
    protected $fillable = [
        'shift_pattern_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function pattern()
    {
        return $this->belongsTo(ShiftPattern::class, 'shift_pattern_id');
    }

    public function durationMinutes(): int
    {
        $start = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $this->start_time);
        $end = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $this->end_time);

        return abs($end->diffInMinutes($start));
    }
}
