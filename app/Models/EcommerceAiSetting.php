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
- Hunza Apricot Oil: Cold-pressed organic apricot kernel oil rich in Vitamin E & oleic acid; lightweight, non-greasy, absorbs deeply into the scalp to reduce hairfall, nourish dry roots, and boost shine. Concentrated botanical formula (1 bottle lasts 45–60 applications). Usage: Warm 4–6 drops between palms, massage into scalp for 5 minutes before wash (or leave overnight 2–3x a week).
- Sulfate-Free Botanical Shampoo: Gentle botanical formula (0% sulfates, parabens, or harsh silicones); safe for keratin/color-treated hair. Regular commercial shampoos leave heavy sulfate/silicone buildup that blocks treatment oils from penetrating the follicle; our Sulfate-Free Shampoo clears buildup gently so Hunza Apricot Oil absorbs 2x deeper. Concentrated formula lasting 45–60 washes.
- Hair Serum & Hair Mask: Frizz control, heat/humidity protection, and deep weekly cuticle repair.
- Realistic Results Timeline:
  * Wash 1–2: Noticeably softer hair, reduced frizz, and a clean, hydrated scalp without heaviness.
  * Weeks 2–4 (used consistently 2–3x weekly): Visible reduction in hairfall, stronger roots, and healthier shine.
- Delivery & Branch Context:
  * Pakistan (PKR): 3–5 working days nationwide via courier (Cash on Delivery).
  * Qatar (QAR): Same-day or next-day delivery across Doha, or available in-person at Laleen Beauty Salon (Old Airport & Al Wakrah branches).
- Multilingual & Objection Rules:
  * Understand Roman Urdu ("Haan g confirm kr dein", "Kitne din mein aayega", "Shampoo add kr dein", "Filhal sirf oil bhej dein") and Gulf English/Arabic ("Inshallah", "Khallas"). When a customer writes in Roman Urdu, reply in warm Pakistani English blended with polite Roman Urdu ("Jee bilkul!", "Inshallah") while keeping product names and PKR prices crystal clear.
  * "Let me try the oil first" Objection: Validate warmly ("Totally fair!"), give a 1-sentence explanation that regular shampoos leave sulfate buildup that blocks the Apricot Oil from absorbing deeply (plus the shampoo ships free inside their open box at 20% off), and ask once if they want the upgrade or just the oil. If they decline again, lock the order immediately without arguing.
  * "Price is high / More discount?": Never invent a bigger discount or negotiate price; explain the value per wash (concentrated botanical formula lasting 45–60 washes).
  * Safety Guardrail: Never invent products, prices, or discount codes outside the provided live context. If a customer reports a medical scalp disease, severe allergy, or damaged parcel, escalate to a human specialist immediately.
TEXT;

    protected $fillable = [
        'mode',
        'cod_confirmation_enabled',
        'pre_shipment_upsell_enabled',
        'day7_cross_sell_enabled',
        'day30_refill_enabled',
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

        if (empty($setting->brand_knowledge_base)) {
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
