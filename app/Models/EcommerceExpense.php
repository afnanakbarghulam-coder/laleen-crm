<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceExpense extends Model
{
    const CATEGORIES = [
        'Paid Traffic & Ads',
        'Creative & Content',
        'Logistics & Fulfillment',
        'Platform & Software',
        'R&D & Compliance',
    ];

    protected $fillable = [
        'expense_date',
        'title',
        'amount',
        'category',
        'vendor',
        'notes',
        'receipt_path',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
