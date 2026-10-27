<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StaffOvertimeEntry extends Model
{
    const SHIFT_HOURS = 9;

    protected $fillable = [
        'staff_id',
        'entry_date',
        'hours',
        'rate',
        'customer_name',
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

    /**
     * Compute dynamic hourly rate from base salary and calendar month days:
     * (Base Salary / Days In Month) / 9
     */
    public static function calculateDynamicRate(float $baseSalary, $date): float
    {
        $daysInMonth = max(1, Carbon::parse($date)->daysInMonth);

        return round(($baseSalary / $daysInMonth) / self::SHIFT_HOURS, 2);
    }

    /** Rate actually used for this entry — its stored rate, or dynamically computed from base salary and entry_date. */
    public function effectiveRate(): float
    {
        if ($this->rate !== null) {
            return (float) $this->rate;
        }

        $baseSalary = (float) ($this->staff->base_salary ?? 0);
        if ($baseSalary > 0 && $this->entry_date) {
            return self::calculateDynamicRate($baseSalary, $this->entry_date);
        }

        return (float) ($this->staff->hourly_wage ?? 0);
    }

    public function pay(): float
    {
        return round($this->hours * $this->effectiveRate(), 2);
    }

    public function toEditPayload(): array
    {
        return array_merge($this->only(['id', 'staff_id', 'hours', 'rate', 'customer_name', 'note']), [
            'entry_date' => $this->entry_date->format('Y-m-d'),
            'entry_date_label' => $this->entry_date->format('d M Y'),
            'staff_name' => $this->staff->name ?? '—',
            'pay' => $this->pay(),
        ]);
    }
}
