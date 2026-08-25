<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyTracker extends Model
{
    protected $fillable = [
        'date',
        'shift',
        'agent_id',
        'check_in',
        'check_out',
        'sent_reminders',
        'asked_feedbacks',
        'updated_no_shows',
        'excel_reviewed',
        'checked_bookings_vs_sales',
        'corrections_done',
        'leads_received',
        'bookings_done',
        'notes'
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
