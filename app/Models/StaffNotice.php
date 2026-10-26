<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffNotice extends Model
{
    const TYPES = [
        'Verbal Warning',
        'Written Warning',
        'Final Notice',
        'Termination Notice',
    ];

    protected $fillable = [
        'staff_id',
        'complaint_id',
        'notice_date',
        'type',
        'subject',
        'description',
        'corrective_actions',
        'acknowledged',
        'created_by',
    ];

    protected $casts = [
        'notice_date' => 'date',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function complaint()
    {
        return $this->belongsTo(StaffComplaint::class, 'complaint_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function toEditPayload(): array
    {
        $complaint = $this->complaint;

        return array_merge($this->only(['id', 'staff_id', 'complaint_id', 'type', 'subject', 'description', 'corrective_actions', 'acknowledged']), [
            'notice_date' => $this->notice_date->format('Y-m-d'),
            'notice_date_label' => $this->notice_date->format('d M Y'),
            'staff_name' => $this->staff->name ?? '—',
            'complaint_reference' => $complaint->reference_number ?? null,
            'client_name' => $complaint?->customer?->name ?? $complaint?->customer_name ?? '—',
            'service_received' => $complaint?->service?->name ?? '—',
            'appt_date' => $complaint?->complaint_date?->format('d M Y') ?? '—',
        ]);
    }
}
