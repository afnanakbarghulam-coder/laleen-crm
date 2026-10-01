<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTransaction extends Model
{
    const CATEGORIES = [
        'Initial Inventory',
        'Ad Budget',
        'Machinery/Filling Equipment',
        'Packaging Run',
        'Other',
    ];

    protected $fillable = [
        'partner_id',
        'type',
        'amount',
        'category',
        'reference_note',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
