<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiStaffSalesEntry extends Model
{
    protected $fillable = [
        'report_id',
        'staff_name',
        'total_upsell',
    ];

    public function report()
    {
        return $this->belongsTo(KpiStaffSalesReport::class, 'report_id');
    }
}
