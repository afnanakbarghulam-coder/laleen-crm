<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MonthlyOffer extends Model
{
    use HasFactory;

    public const TYPE_COMBO = 'combo';
    public const TYPE_SERVICE_FREEBIE = 'service_freebie';
    public const TYPE_STANDALONE = 'standalone';

    public const OFFER_TYPES = [
        self::TYPE_COMBO => 'Linked Combo',
        self::TYPE_SERVICE_FREEBIE => 'Linked Service + Freebies',
        self::TYPE_STANDALONE => 'Standalone Monthly Promo',
    ];

    public const TYPE_BADGES = [
        self::TYPE_COMBO => 'Combo',
        self::TYPE_SERVICE_FREEBIE => 'Service + Freebie',
        self::TYPE_STANDALONE => 'Monthly Promo',
    ];

    public const BRANCH_SCOPES = [
        'all' => 'All Branches',
        'old_airport' => 'Old Airport',
        'al_wakrah' => 'Al Wakrah',
    ];

    protected $table = 'monthly_offers';

    protected $fillable = [
        'title',
        'offer_type',
        'combo_id',
        'service_freebie_id',
        'promo_price_qar',
        'original_value_qar',
        'branch_scope',
        'valid_from',
        'valid_until',
        'terms_and_conditions',
        'staff_notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'combo_id' => 'integer',
        'service_freebie_id' => 'integer',
        'promo_price_qar' => 'float',
        'original_value_qar' => 'float',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'combo_id');
    }

    public function freebiePackage(): BelongsTo
    {
        return $this->belongsTo(ServiceFreebiePackage::class, 'service_freebie_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeBadgeLabel(): string
    {
        return self::TYPE_BADGES[$this->offer_type] ?? 'Monthly Promo';
    }

    public function branchScopeLabel(): string
    {
        return self::BRANCH_SCOPES[$this->branch_scope] ?? 'All Branches';
    }

    /**
     * Live promo price: pulls live price from linked Combo or Service+Freebie when linked,
     * falling back to `promo_price_qar`.
     */
    public function effectivePrice(): float
    {
        if ($this->offer_type === self::TYPE_COMBO && $this->combo) {
            return round((float) $this->combo->price, 2);
        }

        if ($this->offer_type === self::TYPE_SERVICE_FREEBIE && $this->freebiePackage) {
            return round((float) $this->freebiePackage->price, 2);
        }

        return round((float) ($this->promo_price_qar ?? 0), 2);
    }

    public function effectiveOriginalValue(): ?float
    {
        if ($this->original_value_qar !== null && (float) $this->original_value_qar > 0) {
            return round((float) $this->original_value_qar, 2);
        }

        if ($this->offer_type === self::TYPE_COMBO && $this->combo) {
            $sum = round((float) $this->combo->services->sum('price'), 2);

            return $sum > $this->effectivePrice() ? $sum : null;
        }

        return null;
    }

    /**
     * Structured list of included services (live-synced from Combo or Service + Freebies).
     *
     * @return list<array{name: string, is_free: bool, meta: ?string}>
     */
    public function includedServicesList(): array
    {
        $items = [];

        if ($this->offer_type === self::TYPE_COMBO && $this->combo) {
            $poolSize = $this->combo->services->count();
            foreach ($this->combo->services as $svc) {
                $items[] = [
                    'name' => $svc->name,
                    'is_free' => false,
                    'meta' => $svc->duration ? ($svc->duration . ' min') : null,
                ];
            }
            if ($this->combo->quantity_included && $this->combo->quantity_included < $poolSize) {
                array_unshift($items, [
                    'name' => "Choose any {$this->combo->quantity_included} of {$poolSize} services:",
                    'is_free' => false,
                    'meta' => 'Combo Pool',
                ]);
            }

            return $items;
        }

        if ($this->offer_type === self::TYPE_SERVICE_FREEBIE && $this->freebiePackage) {
            $pkg = $this->freebiePackage;
            if ($pkg->anchorService) {
                $items[] = [
                    'name' => $pkg->anchorService->name,
                    'is_free' => false,
                    'meta' => 'Paid Anchor Service',
                ];
            }
            $choices = $pkg->effective_choices_count;
            foreach ($pkg->eligibleServices as $svc) {
                $items[] = [
                    'name' => $svc->name,
                    'is_free' => true,
                    'meta' => $choices > 0 ? "Choose {$choices} Free" : 'FREE',
                ];
            }

            return $items;
        }

        return $items;
    }

    /**
     * Split Terms & Conditions into crisp bullet lines.
     *
     * @return list<string>
     */
    public function termsLines(): array
    {
        $raw = preg_split('/\r\n|\r|\n/', (string) $this->terms_and_conditions) ?: [];
        $lines = [];
        foreach ($raw as $line) {
            $clean = trim(ltrim(trim($line), "•-* "));
            if ($clean !== '') {
                $lines[] = $clean;
            }
        }

        return $lines;
    }

    /**
     * Evaluate active/upcoming/expired/archived status in Asia/Qatar.
     *
     * @return array{key: string, label: string, tone: string}
     */
    public function liveStatus(?Carbon $todayQatar = null): array
    {
        $today = ($todayQatar ?: Carbon::now('Asia/Qatar'))->copy()->startOfDay();
        $from = $this->valid_from ? $this->valid_from->copy()->startOfDay() : $today;
        $until = $this->valid_until ? $this->valid_until->copy()->startOfDay() : $today;

        if (!$this->is_active) {
            return ['key' => 'archived', 'label' => 'Archived', 'tone' => 'muted'];
        }

        if ($today->lt($from)) {
            return ['key' => 'upcoming', 'label' => 'Upcoming · Starts ' . $from->format('d M'), 'tone' => 'amber'];
        }

        if ($today->gt($until)) {
            return ['key' => 'expired', 'label' => 'Expired · Ended ' . $until->format('d M'), 'tone' => 'muted'];
        }

        return ['key' => 'active', 'label' => 'Active · Ends ' . $until->format('d M'), 'tone' => 'emerald'];
    }

    public function whatsappCopyText(): string
    {
        $price = number_format($this->effectivePrice(), 0);
        $lines = [
            "✨ *{$this->title}* — Laleen Beauty Salon",
            "💰 Price: *{$price} QAR*",
            "📍 Branch: {$this->branchScopeLabel()}",
            '📅 Valid Until: ' . ($this->valid_until ? $this->valid_until->format('d M Y') : ''),
        ];

        $included = $this->includedServicesList();
        if (!empty($included)) {
            $lines[] = '';
            $lines[] = '💆‍♀️ *Includes:*';
            foreach ($included as $inc) {
                $suffix = $inc['is_free'] ? ' (FREE)' : '';
                $lines[] = '• ' . $inc['name'] . $suffix;
            }
        }

        $terms = $this->termsLines();
        if (!empty($terms)) {
            $lines[] = '';
            $lines[] = '📋 *Terms & Conditions:*';
            foreach ($terms as $t) {
                $lines[] = '• ' . $t;
            }
        }

        return implode("\n", $lines);
    }
}
