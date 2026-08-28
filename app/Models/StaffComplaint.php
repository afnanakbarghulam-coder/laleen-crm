<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StaffComplaint extends Model
{
    const CATEGORIES = [
        'Service Quality',
        'Staff Behavior',
        'Wait Time',
        'Pricing / Billing',
        'Cleanliness / Facility',
        'Product Issue',
        'Other',
    ];

    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    protected $fillable = [
        'reference_number',
        'complaint_date',
        'complaint_time',
        'branch',
        'customer_id',
        'customer_name',
        'customer_phone',
        'service_id',
        'category',
        'description',
        'deduction_applied',
        'deduction_amount',
        'created_by',
    ];

    protected $casts = [
        'complaint_date' => 'date',
        'deduction_amount' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffComplaint $complaint) {
            if (empty($complaint->reference_number)) {
                $complaint->reference_number = static::generateReferenceNumber($complaint->complaint_date ?? now());
            }
        });
    }

    /** Next sequential CMP-{year}-{3-digit} reference for the given date's year. */
    public static function generateReferenceNumber($date): string
    {
        $year = Carbon::parse($date)->year;
        $count = static::where('reference_number', 'like', "CMP-{$year}-%")->count();

        return sprintf('CMP-%d-%03d', $year, $count + 1);
    }

    /** Staff members involved — a complaint may name more than one. */
    public function staffMembers()
    {
        return $this->belongsToMany(Staff::class, 'complaint_staff', 'staff_complaint_id', 'staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deductions()
    {
        return $this->hasMany(StaffDeduction::class, 'complaint_id');
    }

    public function notices()
    {
        return $this->hasMany(StaffNotice::class, 'complaint_id');
    }

    public function timeLabel(): string
    {
        return $this->complaint_time ? Carbon::parse($this->complaint_time)->format('h:i A') : '—';
    }

    public function toEditPayload(): array
    {
        return array_merge($this->only([
            'id', 'reference_number', 'branch', 'customer_id', 'customer_name',
            'customer_phone', 'service_id', 'category', 'description',
            'deduction_applied', 'deduction_amount',
        ]), [
            'complaint_date' => $this->complaint_date->format('Y-m-d'),
            'complaint_date_label' => $this->complaint_date->format('d M Y'),
            'complaint_time' => $this->complaint_time,
            'time_label' => $this->timeLabel(),
            'staff_ids' => $this->staffMembers->pluck('id')->values(),
            'staff_names' => $this->staffMembers->pluck('name')->implode(', ') ?: '—',
            'service_name' => $this->service->name ?? '—',
            'notice_count' => $this->notices()->count(),
        ]);
    }
}
