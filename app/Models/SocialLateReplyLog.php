<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialLateReplyLog extends Model
{
    protected $fillable = [
        'incident_at',
        'screenshot_path',
        'notes',
        'logged_by',
    ];

    protected $casts = [
        'incident_at' => 'datetime',
    ];

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function screenshotUrl(): ?string
    {
        if (blank($this->screenshot_path)) {
            return null;
        }

        return route('kpi.late-replies.screenshot', $this);
    }
}
