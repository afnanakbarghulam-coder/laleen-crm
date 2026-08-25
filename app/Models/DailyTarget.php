<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTarget extends Model
{
    protected $fillable = [
        'date',
        'daily_target',
        'actual_bookings',
        'percentage_achieved',
        'notes'
    ];
}
