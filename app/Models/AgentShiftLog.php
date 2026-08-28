<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AgentShiftLog extends Model
{
    const SHIFTS = [
        'morning' => 'Morning',
        'evening' => 'Evening',
    ];

    protected $fillable = [
        'user_id',
        'date',
        'shift',
        'check_in_time',
        'check_out_time',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The agent's exact check-in timestamp on this date. A booking is only
     * attributed to this shift if it falls strictly between this and
     * windowEnd() — no fixed 07:00/14:30-style clock assumption.
     */
    public function windowStart(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->check_in_time);
    }

    /**
     * The agent's exact check-out timestamp on this date.
     */
    public function windowEnd(): Carbon
    {
        return $this->date->copy()->setTimeFromTimeString($this->check_out_time);
    }

    /**
     * Compact payload for the edit-modal JS. A single-argument call (no
     * inline array literal) so Blade's @json directive — which naively
     * explode(',')s its expression — doesn't truncate on commas.
     */
    public function toEditPayload(): array
    {
        return $this->only(['id', 'date', 'user_id', 'shift', 'check_in_time', 'check_out_time']);
    }
}
