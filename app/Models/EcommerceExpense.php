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

    const FUNDING_SOURCES = [
        'partner_ledger' => 'Partner Ledger (Total Pool)',
        'sales' => 'Sales Revenue',
    ];

    // True non-BOM operating expenses deducted from Gross Profit on the Net
    // Profit dashboard.
    //
    // Physical BOM components (Bottles, Labels, Packaging, Stickers, Raw
    // Material Restock) are deliberately EXCLUDED: those purchases already
    // leave the business twice-over in the correct places — against Cash
    // Runway the moment they're bought, and against Net Profit via each
    // sale's unit_cogs snapshot once the finished unit actually sells.
    // Listing them here as well deducted the same rupee from profit twice.
    const NET_PROFIT_DEDUCTION_CATEGORIES = [
        'Courier/Shipping',
        'Taxes',
        'Meta Ads',
        'Paid Traffic & Ads',
        'Creative & Content',
        'Logistics & Fulfillment',
        'Platform & Software',
        'R&D & Compliance',
        'Other Operating',
    ];

    // Categories that represent inventory/BOM purchases. They fund Tier 1
    // stock and hit profit through unit_cogs, never as an operating expense.
    const BOM_INVENTORY_CATEGORIES = [
        'Bottles',
        'Labels',
        'Packaging',
        'Stickers',
        'Raw Material Restock',
    ];

    /** Shared explainer rendered on the Expenses and Net Profit tabs. */
    const BOM_ACCOUNTING_NOTE = 'Inventory/BOM restocks (Bottles, Labels, Packaging, Liquid) deduct from Cash Runway on purchase and from Net Profit via Unit COGS when sold.';

    protected $fillable = [
        'expense_date',
        'title',
        'amount',
        'category',
        'funding_source',
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
