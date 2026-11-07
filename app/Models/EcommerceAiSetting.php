<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommerceAiSetting extends Model
{
    use HasFactory;

    protected $table = 'ecommerce_ai_settings';

    public const MODES = [
        'copilot',
        'autopilot',
    ];

    public const DRIVERS = [
        'simulator',
        'webhook',
        'meta_cloud',
    ];

    public const DEFAULT_KNOWLEDGE_BASE = <<<'TEXT'
- Hunza Apricot Oil: 100% pure cold-pressed organic Hunza apricot kernel oil rich in Vitamin E & oleic acid (zero cheap mineral oil or synthetic fragrance dilutions); lightweight, non-greasy, absorbs cleanly into the scalp to reduce hairfall, nourish dry roots, and boost shine. Concentrated botanical formula (1 bottle lasts 45–60 applications). Usage: Warm 4–6 drops between palms, massage into scalp for 5 minutes before wash (or leave overnight 2–3x a week).
- Sulfate-Free Botanical Shampoo: Gentle botanical formula (0% sulfates, parabens, harsh salts, or heavy silicones); safe for keratin/color-treated hair. Regular commercial shampoos leave heavy sulfate/silicone buildup that blocks treatment oils from penetrating the follicle; our Sulfate-Free Shampoo clears buildup gently so Hunza Apricot Oil absorbs 2x deeper. Concentrated formula lasting 45–60 washes.
- Hair Serum & Hair Mask: Frizz control, heat/humidity protection, and deep weekly cuticle repair.
- Oily vs. Dry Scalp Application: For oily scalps, massage 3–4 drops of Apricot Oil 1–2 hours before washing and cleanse with Sulfate-Free Shampoo. For dry/damaged scalps, leave overnight 2–3x a week.
- Bottle Size & Longevity: Concentrated formula lasting 45–60 washes (~1.5 to 2 months of regular 2–3x weekly rituals) — less than PKR 40 / QAR 1.5 per wash.
- Men, Beard & Kids Safety: 100% cold-pressed botanical formula; safe and effective for men's scalp/beard nourishment and children aged 5+.
- Color, Keratin & Protein Treated Hair: 100% safe; free from sulfates, parabens, and harsh salts so salon treatments last longer.
- Shelf Life: 12 months after opening when stored in a cool, dry place.
- Packaging & Delivery Logistics: Triple-sealed leak-proof packaging; courier rider always calls ahead before delivery.
  * Pakistan (PKR): 3–5 working days nationwide via courier (Cash on Delivery active; EasyPaisa/JazzCash/Bank Transfer supported via team).
  * Qatar (QAR): Same-day or next-day delivery across Doha, or available in-person at Laleen Beauty Salon (Old Airport & Al Wakrah branches).
- Realistic Results Timeline:
  * Wash 1–2: Noticeably softer hair, reduced frizz, and a clean, hydrated scalp without heaviness.
  * Weeks 2–4 (used consistently 2–3x weekly): Visible reduction in hairfall, stronger roots, and healthier shine.
- Multilingual & Objection Rules:
  * Understand English, Roman Urdu ("Haan g confirm kr dein", "Kitne din mein aayega", "Shampoo add kr dein", "Filhal sirf oil bhej dein", "Kitne ml hai"), native Urdu script ("جی", "ہاں", "کنفرم"), and Gulf English/Arabic ("نعم", "تأكيد", "Inshallah", "Khallas").
  * 1-Pivot Downsell Rule ("Let me try the oil first" / "Too expensive"): Validate warmly, explain the biological mechanism (commercial sulfates block Apricot Oil absorption) + cost-per-wash math (45–60 washes = < PKR 40 / QAR 1.5 per wash), and offer an A/B Pivot: either lock in the 20% off complementary item OR add a 2nd bottle of their ordered hero product at 20% off (for family or next month's supply). If they decline a second time, lock the order immediately with zero pressure.
  * Safety Guardrail: Never invent products, prices, or discount codes outside the provided live context. If a customer reports a medical scalp disease, severe allergy, or damaged parcel, escalate to a human specialist immediately.
TEXT;

    protected $fillable = [
        'mode',
        'cod_confirmation_enabled',
        'pre_shipment_upsell_enabled',
        'day7_cross_sell_enabled',
        'day30_refill_enabled',
        'abandoned_checkout_enabled',
        'abandoned_checkout_delay_minutes',
        'upsell_discount_percent',
        'whatsapp_driver',
        'outbound_webhook_url',
        'agent_persona_name',
        'shopify_store_domain',
        'day7_discount_code',
        'day30_discount_code',
        'brand_knowledge_base',
    ];

    protected $casts = [
        'cod_confirmation_enabled' => 'boolean',
        'pre_shipment_upsell_enabled' => 'boolean',
        'day7_cross_sell_enabled' => 'boolean',
        'day30_refill_enabled' => 'boolean',
        'abandoned_checkout_enabled' => 'boolean',
        'abandoned_checkout_delay_minutes' => 'integer',
        'upsell_discount_percent' => 'decimal:2',
    ];

    /**
     * Retrieve or initialize the singleton configuration row.
     */
    public static function current(): self
    {
        $setting = self::firstOrCreate(
            [],
            [
                'mode' => 'copilot',
                'cod_confirmation_enabled' => true,
                'pre_shipment_upsell_enabled' => true,
                'day7_cross_sell_enabled' => true,
                'day30_refill_enabled' => true,
                'abandoned_checkout_enabled' => true,
                'abandoned_checkout_delay_minutes' => 15,
                'upsell_discount_percent' => 20.00,
                'whatsapp_driver' => 'simulator',
                'outbound_webhook_url' => null,
                'agent_persona_name' => 'Zara',
                'shopify_store_domain' => 'laleenrituals.com',
                'day7_discount_code' => 'COMPLETE20',
                'day30_discount_code' => 'VIPREFILL15',
                'brand_knowledge_base' => self::DEFAULT_KNOWLEDGE_BASE,
            ]
        );

        if (
            empty($setting->brand_knowledge_base)
            || !str_contains((string) $setting->brand_knowledge_base, 'Oily vs. Dry Scalp Application')
        ) {
            $setting->brand_knowledge_base = self::DEFAULT_KNOWLEDGE_BASE;
            $setting->save();
        }

        return $setting;
    }

    public function isCopilot(): bool
    {
        return $this->mode !== 'autopilot';
    }

    public function isAutopilot(): bool
    {
        return $this->mode === 'autopilot';
    }
}
