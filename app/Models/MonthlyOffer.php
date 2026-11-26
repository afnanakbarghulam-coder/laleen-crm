<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        self::TYPE_STANDALONE => 'Selected Service(s) / Standalone Offer',
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

    /**
     * Canonical 7-Pillar Master Offer Architecture definitions in exact strategic order.
     *
     * @var list<array{number: int, key: string, label: string, short_label: string, hint: string, placeholder: string}>
     */
    public const PILLAR_DEFINITIONS = [
        [
            'number' => 1,
            'key' => 'dream_outcome',
            'label' => 'Dream Outcome (Real Results)',
            'short_label' => 'Dream Outcome',
            'hint' => 'Captures attention and sells the end state, not just the service.',
            'placeholder' => 'e.g. Glass-smooth, frizz-free hair that holds mirror shine for up to 12 weeks in Doha humidity.',
        ],
        [
            'number' => 2,
            'key' => 'problem_solved',
            'label' => 'Problem Solved (Before & After)',
            'short_label' => 'Problem Solved',
            'hint' => "Calls out the client's exact pain so she feels understood.",
            'placeholder' => 'e.g. Ends daily flat-iron heat damage, puffy roots, and dry brittle ends after coloring or desalinated water.',
        ],
        [
            'number' => 3,
            'key' => 'core_value_additions',
            'label' => 'Core Value Additions',
            'short_label' => 'Core Value Additions',
            'hint' => 'Elevates the experience from a basic service to bespoke care.',
            'placeholder' => 'e.g. Scalp & strand diagnostic consult, custom protein blend, steam infusion, and precision trim.',
        ],
        [
            'number' => 4,
            'key' => 'value_stack_bonuses',
            'label' => 'Value-Stack Bonuses',
            'short_label' => 'Value-Stack Bonuses',
            'hint' => 'Creates an irresistible value-to-price ratio (complimentary add-ons/bonuses).',
            'placeholder' => 'e.g. FREE clarifying scalp detox (120 QAR value) + FREE signature blowout finish + travel aftercare serum.',
        ],
        [
            'number' => 5,
            'key' => 'conditional_guarantee',
            'label' => 'Conditional Guarantee',
            'short_label' => 'Conditional Guarantee',
            'hint' => 'Removes buyer fear while protecting the salon (clear conditions).',
            'placeholder' => 'e.g. 7-Day Silk Satisfaction Promise — complimentary touch-up if frizz returns when using sulfate-free care.',
        ],
        [
            'number' => 6,
            'key' => 'transparent_pricing',
            'label' => 'Transparent Pricing',
            'short_label' => 'Transparent Pricing',
            'hint' => 'Low-friction entry point with zero chair surprises (e.g. hair length tiers).',
            'placeholder' => 'e.g. Short/Shoulder: 299 QAR · Mid-Back: 399 QAR · Extra Long/Thick: 499 QAR (confirmed before starting).',
        ],
        [
            'number' => 7,
            'key' => 'capacity_scarcity',
            'label' => 'Capacity Scarcity',
            'short_label' => 'Capacity Scarcity',
            'hint' => 'Drives immediate booking action (e.g. Strictly limited to 25 slots per week).',
            'placeholder' => 'e.g. Strictly limited to 25 slots per week across both branches due to 2-hour senior specialist chair time.',
        ],
    ];

    protected $table = 'monthly_offers';

    protected $fillable = [
        'title',
        'offer_type',
        'combo_id',
        'service_freebie_id',
        'promo_price_qar',
        'branch_scope',
        'valid_from',
        'valid_until',
        'terms_and_conditions',
        'staff_notes',
        'dream_outcome',
        'problem_solved',
        'core_value_additions',
        'value_stack_bonuses',
        'conditional_guarantee',
        'transparent_pricing',
        'capacity_scarcity',
        'whatsapp_script',
        'video_shooting_script',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'combo_id' => 'integer',
        'service_freebie_id' => 'integer',
        'promo_price_qar' => 'float',
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

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'monthly_offer_service', 'monthly_offer_id', 'service_id')
            ->withTimestamps()
            ->orderBy('services.name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeBadgeLabel(): string
    {
        if ($this->offer_type === self::TYPE_STANDALONE && $this->services->isNotEmpty()) {
            return $this->services->count() === 1 ? 'Selected Service' : 'Selected Services';
        }

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

    /**
     * Structured list of included services (live-synced from Combo, Service + Freebies, or attached Catalog Services).
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

        if ($this->services->isNotEmpty()) {
            foreach ($this->services as $svc) {
                $metaParts = array_filter([
                    $svc->relationLoaded('category') ? $svc->category?->name : null,
                    $svc->duration ? ($svc->duration . ' min') : null,
                ]);
                $items[] = [
                    'name' => $svc->name,
                    'is_free' => false,
                    'meta' => !empty($metaParts) ? implode(' · ', $metaParts) : null,
                ];
            }
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

    public function filledPillarsCount(): int
    {
        $count = 0;
        foreach (self::PILLAR_DEFINITIONS as $def) {
            if (filled($this->{$def['key']})) {
                $count++;
            }
        }

        return $count;
    }

    public function hasWhatsappScript(): bool
    {
        return filled($this->whatsapp_script);
    }

    public function hasVideoScript(): bool
    {
        return filled($this->video_shooting_script);
    }

    /**
     * @return list<array{number: int, key: string, label: string, short_label: string, hint: string, value: ?string}>
     */
    public function pillarsList(): array
    {
        $list = [];
        foreach (self::PILLAR_DEFINITIONS as $def) {
            $val = trim((string) ($this->{$def['key']} ?? ''));
            $list[] = [
                'number' => $def['number'],
                'key' => $def['key'],
                'label' => $def['label'],
                'short_label' => $def['short_label'],
                'hint' => $def['hint'],
                'value' => $val !== '' ? $val : null,
            ];
        }

        return $list;
    }

    public function autoGeneratedWhatsappPitch(): string
    {
        $price = number_format($this->effectivePrice(), 0);
        $lines = [
            "✨ *{$this->title}* — Laleen Beauty Salon",
            "💰 Price: *{$price} QAR*",
            "📍 Branch: {$this->branchScopeLabel()}",
            '📅 Valid Until: ' . ($this->valid_until ? $this->valid_until->format('d M Y') : ''),
        ];

        if (filled($this->dream_outcome)) {
            $lines[] = '';
            $lines[] = '🌟 ' . trim((string) $this->dream_outcome);
        }

        $included = $this->includedServicesList();
        if (!empty($included)) {
            $lines[] = '';
            $lines[] = '💆‍♀️ *Includes:*';
            foreach ($included as $inc) {
                $suffix = $inc['is_free'] ? ' (FREE)' : '';
                $lines[] = '• ' . $inc['name'] . $suffix;
            }
        }

        if (filled($this->conditional_guarantee)) {
            $lines[] = '';
            $lines[] = '🛡️ *Guarantee:* ' . trim((string) $this->conditional_guarantee);
        }

        if (filled($this->capacity_scarcity)) {
            $lines[] = '⏳ *Availability:* ' . trim((string) $this->capacity_scarcity);
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

    /**
     * Primary copy text for the quick "Copy WhatsApp Script" button:
     * returns the saved custom WhatsApp script directly if present,
     * otherwise falls back to the auto-formatted client summary.
     */
    public function whatsappCopyText(): string
    {
        if ($this->hasWhatsappScript()) {
            return trim((string) $this->whatsapp_script);
        }

        return $this->autoGeneratedWhatsappPitch();
    }

    /**
     * Complete JSON view-model for the Offer Playbook Slide-Over Drawer & Edit Modal.
     *
     * @return array<string, mixed>
     */
    public function toPlaybookPayload(?Carbon $todayQatar = null): array
    {
        $status = $this->liveStatus($todayQatar);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'offer_type' => $this->offer_type,
            'type_label' => $this->typeBadgeLabel(),
            'combo_id' => $this->combo_id,
            'service_freebie_id' => $this->service_freebie_id,
            'service_ids' => $this->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'promo_price_qar' => $this->promo_price_qar,
            'effective_price_qar' => $this->effectivePrice(),
            'branch_scope' => $this->branch_scope,
            'branch_label' => $this->branchScopeLabel(),
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'valid_range_label' => ($this->valid_from?->format('d M Y') ?? '') . ' – ' . ($this->valid_until?->format('d M Y') ?? ''),
            'status' => $status,
            'is_active' => (bool) $this->is_active,
            'included_services' => $this->includedServicesList(),
            'terms_and_conditions' => $this->terms_and_conditions,
            'terms_lines' => $this->termsLines(),
            'staff_notes' => $this->staff_notes,
            'dream_outcome' => $this->dream_outcome,
            'problem_solved' => $this->problem_solved,
            'core_value_additions' => $this->core_value_additions,
            'value_stack_bonuses' => $this->value_stack_bonuses,
            'conditional_guarantee' => $this->conditional_guarantee,
            'transparent_pricing' => $this->transparent_pricing,
            'capacity_scarcity' => $this->capacity_scarcity,
            'pillars' => $this->pillarsList(),
            'filled_pillars_count' => $this->filledPillarsCount(),
            'whatsapp_script' => $this->whatsapp_script,
            'has_whatsapp_script' => $this->hasWhatsappScript(),
            'whatsapp_copy_text' => $this->whatsappCopyText(),
            'auto_whatsapp_pitch' => $this->autoGeneratedWhatsappPitch(),
            'video_shooting_script' => $this->video_shooting_script,
            'has_video_script' => $this->hasVideoScript(),
        ];
    }
}
