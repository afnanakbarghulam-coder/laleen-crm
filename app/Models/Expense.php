<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    const CATEGORIES = [
        'Rent',
        'Utilities',
        'Salaries & Wages',
        'Supplies',
        'Marketing',
        'Maintenance',
        'Other',
    ];

    protected $fillable = [
        'branch',
        'category',
        'amount',
        'expense_date',
        'description',
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
