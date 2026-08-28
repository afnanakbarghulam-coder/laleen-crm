<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffOvertimeEntry extends Model
{
    protected $fillable = [
        'staff_id',
        'entry_date',
        'hours',
        'rate',
        'note',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'hours' => 'float',
        'rate' => 'float',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Rate actually used for this entry — its own override, or the staff member's hourly wage. */
    public function effectiveRate(): float
    {
        return (float) ($this->rate ?? $this->staff->hourly_wage ?? 0);
    }

    public function pay(): float
    {
        return round($this->hours * $this->effectiveRate(), 2);
    }

    public function toEditPayload(): array
    {
        return array_merge($this->only(['id', 'staff_id', 'hours', 'rate', 'note']), [
            'entry_date' => $this->entry_date->format('Y-m-d'),
            'entry_date_label' => $this->entry_date->format('d M Y'),
            'staff_name' => $this->staff->name ?? '—',
            'pay' => $this->pay(),
        ]);
    }
}
