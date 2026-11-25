<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class StockItem extends Model
{
    use HasFactory;

    public const TYPE_BACKBAR = 'backbar';
    public const TYPE_RETAIL = 'retail';

    public const TYPES = [
        self::TYPE_BACKBAR => 'Backbar Supplies',
        self::TYPE_RETAIL => 'Retail Products',
    ];

    public const CATEGORIES = [
        'hair_color' => 'Hair Color',
        'treatments' => 'Treatments',
        'nails' => 'Nails',
        'facials' => 'Facials',
        'waxing' => 'Waxing',
        'consumables' => 'Consumables',
        'retail' => 'Retail',
        'other' => 'Other',
    ];

    public const COMMON_UNITS = [
        'Bottle',
        'Tube',
        'Box',
        'Pack',
        'Jar',
        'Piece',
        'Litre',
        'Roll',
    ];

    protected $table = 'stock_items';

    protected $fillable = [
        'name',
        'item_type',
        'product_id',
        'category',
        'unit',
        'par_level_old_airport',
        'par_level_al_wakrah',
        'is_active',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'par_level_old_airport' => 'float',
        'par_level_al_wakrah' => 'float',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockWeekEntry::class, 'stock_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stock_item_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucwords(str_replace('_', ' ', (string) $this->category));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->item_type] ?? ucfirst((string) $this->item_type);
    }

    public function parForBranch(string $branch): float
    {
        $norm = StockWeek::normalizeBranch($branch) ?? 'old_airport';

        return round((float) ($norm === 'al_wakrah' ? $this->par_level_al_wakrah : $this->par_level_old_airport), 2);
    }

    public static function syncFromProduct(Product $product): ?self
    {
        if (!Schema::hasTable('stock_items')) {
            return null;
        }

        $existing = static::where('product_id', $product->id)->first();
        if ($existing) {
            $existing->update([
                'name' => $product->name,
                'item_type' => self::TYPE_RETAIL,
                'category' => 'retail',
                'is_active' => true,
            ]);

            return $existing;
        }

        $defaultPar = max(0.0, (float) ($product->low_stock_threshold ?? 3));

        return static::create([
            'name' => $product->name,
            'item_type' => self::TYPE_RETAIL,
            'product_id' => $product->id,
            'category' => 'retail',
            'unit' => 'Piece',
            'par_level_old_airport' => $defaultPar,
            'par_level_al_wakrah' => $defaultPar,
            'is_active' => true,
        ]);
    }

    public static function deactivateForProduct(Product $product): void
    {
        if (!Schema::hasTable('stock_items')) {
            return;
        }

        static::where('product_id', $product->id)->update(['is_active' => false]);
    }
}
