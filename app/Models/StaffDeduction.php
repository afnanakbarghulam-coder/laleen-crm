<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDeduction extends Model
{
    protected $fillable = [
        'staff_id',
        'deduction_date',
        'amount',
        'reason',
        'complaint_id',
        'created_by',
    ];

    protected $casts = [
        'deduction_date' => 'date',
        'amount' => 'float',
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
        return array_merge($this->only(['id', 'staff_id', 'amount', 'reason', 'complaint_id']), [
            'deduction_date' => $this->deduction_date->format('Y-m-d'),
            'deduction_date_label' => $this->deduction_date->format('d M Y'),
            'staff_name' => $this->staff->name ?? '—',
        ]);
    }
}
