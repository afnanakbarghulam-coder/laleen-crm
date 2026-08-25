<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QaCorrection extends Model
{
    protected $fillable = [
        'agent_id',
        'customer_phone',
        'issue_type',
        'notes',
        'severity',
        'status',
        'appointment_id',
        'proof_file'
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
