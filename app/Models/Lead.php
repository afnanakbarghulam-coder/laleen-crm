<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'assigned_agent_id',
        'lead_source',
        'followup_date',
        'notes',
        'status'
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }
}
