<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    const CATEGORIES = [
        'follow_up' => 'Follow up',
        'inquiry' => 'Inquiry',
        'cancel' => 'Cancel',
    ];

    const CORRECTION_STATUSES = [
        'yes' => 'Yes',
        'no' => 'No',
        'booked' => 'Booked',
    ];

    protected $fillable = [
        'phone',
        'assigned_agent_id',
        'category',
        'customer_remarks',
        'service_interest',
        'booking_status',
        'correction_done',
        'next_followup_date',
    ];

    protected $casts = [
        'next_followup_date' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }
}
