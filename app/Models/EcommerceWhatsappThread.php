<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceWhatsappThread extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_whatsapp_threads';

    public const PLAYBOOKS = [
        'cod_upsell',
        'day7_cross_sell',
        'day30_refill',
        'abandoned_checkout',
        'inbound_support',
    ];

    public const STATES = [
        'idle',
        'inbound_active',
        'awaiting_cod_confirm',
        'cod_confirmed_upsell_pitched',
        'upsell_accepted',
        'upsell_declined',
        'day7_checkin_sent',
        'day7_cross_sell_pitched',
        'day30_refill_pitched',
        'abandoned_followup_sent',
        'checkout_link_sent',
        'human_handoff',
        'opted_out',
        'completed',
    ];

    public const STATE_LABELS = [
        'idle' => 'Idle',
        'inbound_active' => 'Inbound Concierge 💬',
        'awaiting_cod_confirm' => 'Awaiting COD Confirm',
        'cod_confirmed_upsell_pitched' => 'Upsell Pitched',
        'upsell_accepted' => 'Upsell Won 💰',
        'upsell_declined' => 'Order Locked',
        'day7_checkin_sent' => 'Day 7 Check-In Sent',
        'day7_cross_sell_pitched' => 'Day 7 Cross-Sell Pitched',
        'day30_refill_pitched' => 'Day 30 Refill Pitched',
        'abandoned_followup_sent' => 'Abandoned Cart Rescue Sent 🛒',
        'checkout_link_sent' => 'Checkout Link Sent 🔗',
        'human_handoff' => 'Human Handoff 🚨',
        'opted_out' => 'Opted Out 🔕',
        'completed' => 'Completed',
    ];

    public const PLAYBOOK_LABELS = [
        'cod_upsell' => 'COD Confirm + Box Upgrade',
        'day7_cross_sell' => 'Day 7 Ritual Cross-Sell',
        'day30_refill' => 'Day 30 Refill Check-In',
        'abandoned_checkout' => '15-Min Abandoned Cart Rescue',
        'inbound_support' => 'Inbound Hair Concierge',
    ];

    protected $fillable = [
        'ecommerce_customer_id',
        'order_group_id',
        'customer_name',
        'customer_phone',
        'region',
        'playbook',
        'state',
        'offered_product_id',
        'offered_bundle_id',
        'offered_addon_price',
        'upsell_revenue_generated',
        'unread_count',
        'last_message_at',
        'nudge_sent_at',
        'delivery_notes',
    ];

    protected $casts = [
        'offered_addon_price' => 'decimal:2',
        'upsell_revenue_generated' => 'decimal:2',
        'unread_count' => 'integer',
        'last_message_at' => 'datetime',
        'nudge_sent_at' => 'datetime',
    ];

    protected ?float $virtualOfferedDiscountPercent = null;

    public function getOfferedDiscountPercentAttribute(): float
    {
        return $this->virtualOfferedDiscountPercent ?? 20.0;
    }

    public function setOfferedDiscountPercentAttribute($value): void
    {
        $this->virtualOfferedDiscountPercent = $value !== null ? (float) $value : null;
    }


    public function customer(): BelongsTo
    {
        return $this->belongsTo(EcommerceCustomer::class, 'ecommerce_customer_id');
    }

    public function offeredProduct(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'offered_product_id');
    }

    public function offeredBundle(): BelongsTo
    {
        return $this->belongsTo(EcommerceBundle::class, 'offered_bundle_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EcommerceWhatsappMessage::class, 'thread_id')->orderBy('created_at')->orderBy('id');
    }

    public function scopeForRegion(Builder $query, ?string $region): Builder
    {
        $normalized = strtoupper(EcommerceSale::normalizeRegion($region));

        return $query->where('region', $normalized);
    }

    public function currency(): string
    {
        return strtoupper((string) $this->region) === 'QA' ? 'QAR' : 'PKR';
    }

    public function stateLabel(): string
    {
        return self::STATE_LABELS[$this->state] ?? ucfirst(str_replace('_', ' ', (string) $this->state));
    }

    public function playbookLabel(): string
    {
        return self::PLAYBOOK_LABELS[$this->playbook] ?? ucfirst(str_replace('_', ' ', (string) $this->playbook));
    }

    /**
     * Check whether an order_group_id represents a cold customer chat with no linked order yet.
     */
    public static function isColdChatOrderRef(?string $orderGroupId): bool
    {
        $raw = trim((string) $orderGroupId);

        return $raw === '' || str_starts_with(strtoupper($raw), 'COLD-');
    }

    public function hasRealOrderGroup(): bool
    {
        return !self::isColdChatOrderRef($this->order_group_id);
    }

    /**
     * Turn a raw UUID like `d2871bec-800c-4b0f-8971-0e03c2b976b0` into `#D2871B`
     * or format `SHOPIFY-xxxx` cleanly.
     */
    public function shortOrderRef(): string
    {
        return self::formatShortOrderRef($this->order_group_id);
    }

    public static function formatShortOrderRef(?string $orderGroupId): string
    {
        $raw = trim((string) $orderGroupId);
        if ($raw === '' || str_starts_with(strtoupper($raw), 'COLD-')) {
            return '#ORDER';
        }

        if (stripos($raw, 'SHOPIFY-') === 0) {
            $suffix = substr($raw, 8);
            if (strlen($suffix) > 8) {
                $suffix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $suffix), 0, 6));
            }

            return 'SHOPIFY-' . $suffix;
        }

        $cleanHex = strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', $raw));

        return '#' . substr($cleanHex, 0, 6);
    }

    /**
     * Clean status label + style variant for the minimalist thread list card.
     */
    public function compactStatusBadge(): array
    {
        return match ($this->state) {
            'upsell_accepted' => ['label' => 'Upgraded ✨', 'variant' => 'upgraded'],
            'checkout_link_sent' => ['label' => 'Link Sent 🔗', 'variant' => 'confirmed'],
            'cod_confirmed_upsell_pitched', 'upsell_declined', 'completed' => ['label' => 'Confirmed', 'variant' => 'confirmed'],
            'human_handoff' => ['label' => 'Escalated', 'variant' => 'escalated'],
            'opted_out' => ['label' => 'Opted Out', 'variant' => 'escalated'],
            'inbound_active' => ['label' => 'Concierge 💬', 'variant' => 'awaiting'],
            default => ['label' => 'Awaiting Reply', 'variant' => 'awaiting'],
        };
    }
}
