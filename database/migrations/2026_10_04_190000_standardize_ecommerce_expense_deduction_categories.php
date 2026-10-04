<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aligns existing expense category labels with the standardized Net
     * Profit deduction categories (Stickers, Bottles, Labels, Courier/
     * Shipping, Packaging, Taxes, Meta Ads) so already-logged costs are
     * picked up by the Net Profit dashboard.
     */
    private const RENAMES = [
        'Bottle' => 'Bottles',
        'Label' => 'Labels',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('ecommerce_expenses')->where('category', $from)->update(['category' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $from => $to) {
            DB::table('ecommerce_expenses')->where('category', $to)->update(['category' => $from]);
        }
    }
};
