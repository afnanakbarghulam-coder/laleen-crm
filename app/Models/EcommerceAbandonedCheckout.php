<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceAbandonedCheckout extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_abandoned_checkouts';

    public const STATUSES = [
        'abandoned',
        'outreach_sent',
        'recovered',
        'expired',
    ];

    protected $fillable = [
        'shopify_checkout_id',
        'shopify_checkout_token',
        'ecommerce_customer_id',
        'customer_name',
        'phone',
        'email',
        'region',
        'currency',
        'subtotal_price',
        'abandoned_checkout_url',
        'line_items_json',
        'status',
        'abandoned_at',
        'outreach_sent_at',
        'recovered_at',
        'recovered_order_group_id',
    ];

    protected $casts = [
        'subtotal_price' => 'decimal:2',
        'line_items_json' => 'array',
        'abandoned_at' => 'datetime',
        'outreach_sent_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(EcommerceCustomer::class, 'ecommerce_customer_id');
    }

    /**
     * Format a human-readable summary of the cart line items (e.g., "1x Hunza Apricot Oil, 1x Sulfate-Free Shampoo").
     */
    public function itemsSummaryText(): string
    {
        $items = is_array($this->line_items_json) ? $this->line_items_json : [];
        if (empty($items)) {
            return 'your Laleen hair ritual items';
        }

        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? $item['name'] ?? 'Product'));
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $parts[] = "{$qty}x *{$title}*";
        }

        return !empty($parts) ? implode(', ', $parts) : 'your Laleen hair ritual items';
    }
}
