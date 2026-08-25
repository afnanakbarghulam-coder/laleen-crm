<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffBlock extends Model
{
    protected $fillable = [
        'staff_id',
        'date',
        'start_time',
        'end_time',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
