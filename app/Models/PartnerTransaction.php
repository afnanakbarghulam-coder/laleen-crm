<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        'currency',
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

    public function scopeForRegion(Builder $query, ?string $region): Builder
    {
        $currency = EcommerceSale::currencyForRegion($region);

        return $query->where('currency', $currency);
    }

    /**
     * Returns the 5 default categories merged with all persisted custom categories
     * (case-insensitively deduplicated, preserving canonical casing).
     *
     * @return list<string>
     */
    public static function allCategories(): array
    {
        $seen = [];
        $merged = [];

        $push = static function (?string $raw) use (&$seen, &$merged): void {
            $clean = trim((string) $raw);
            if ($clean === '') {
                return;
            }
            $key = mb_strtolower($clean);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $merged[] = $clean;
            }
        };

        foreach (self::CATEGORIES as $defaultCategory) {
            $push($defaultCategory);
        }

        if (Schema::hasTable('ecommerce_transaction_categories')) {
            foreach (EcommerceTransactionCategory::orderBy('id')->pluck('name') as $savedName) {
                $push($savedName);
            }
        }

        if (Schema::hasTable('partner_transactions')) {
            foreach (static::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category') as $usedCategory) {
                $push($usedCategory);
            }
        }

        return $merged;
    }

    /**
     * Ensures a custom category is persisted in `ecommerce_transaction_categories`
     * if it is not already among the default or saved categories, returning its canonical name.
     */
    public static function ensureCategoryPersisted(string $rawCategory, ?int $userId = null): string
    {
        $trimmed = trim($rawCategory);
        if ($trimmed === '') {
            return '';
        }

        $existing = static::allCategories();
        foreach ($existing as $cat) {
            if (mb_strtolower($cat) === mb_strtolower($trimmed)) {
                return $cat;
            }
        }

        if (Schema::hasTable('ecommerce_transaction_categories')) {
            EcommerceTransactionCategory::firstOrCreate(
                ['name' => $trimmed],
                ['created_by' => $userId]
            );
        }

        return $trimmed;
    }
}
