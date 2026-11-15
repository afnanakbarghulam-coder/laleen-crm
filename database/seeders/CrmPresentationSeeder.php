<?php

namespace Database\Seeders;

use App\Models\CrmPresentation;
use Illuminate\Database\Seeder;

class CrmPresentationSeeder extends Seeder
{
    public const GIVEAWAY_SLUG = 'laleen-5-step-giveaway-growth-playbook';

    public function run(): void
    {
        $attrs = self::giveawayPlaybookAttributes();

        CrmPresentation::updateOrCreate(
            ['slug' => self::GIVEAWAY_SLUG],
            $attrs
        );
    }

    /**
     * Authoritative 6-slide "Laleen Beauty Salon — 1,000 QAR Makeover Giveaway Plan"
     * written in clear, jargon-free team language with Revenue & CRM Growth Potential math.
     *
     * @return array<string, mixed>
     */
    public static function giveawayPlaybookAttributes(): array
    {
        $rawSlides = [
            // Slide 1 (cover)
            [
                'layout' => 'cover',
                'eyebrow' => 'TEAM PLAN · LALEEN BEAUTY SALON',
                'title' => '1,000 QAR Makeover Giveaway Plan',
                'subtitle' => 'We run a 7-day giveaway where women enter their Contact Number & Email to win a FREE 1,000 QAR Makeover. Every sign-up goes straight into our CRM, and we spend the next 3 days turning runners-up into 500 QAR bookings.',
                'highlight_metric' => 'Old Airport & Al Wakrah',
                'body_points' => [
                    'We run a 7-day giveaway where women enter their Contact Number & Email to win a FREE 1,000 QAR Makeover. Every sign-up goes straight into our CRM, and we spend the next 3 days turning runners-up into 500 QAR bookings.',
                ],
                'table_rows' => [],
                'footer_note' => 'Laleen Beauty Salon · Quick 6-Slide Team Guide',
            ],
            // Slide 2 (comparison)
            [
                'layout' => 'comparison',
                'eyebrow' => 'SLIDE 02 · THE OFFER',
                'highlight_metric' => '1,000 QAR Value',
                'title' => '1st Prize vs. 2nd Prize',
                'subtitle' => '1st Prize gets women excited to join and invite friends; 2nd Prize brings immediate bookings.',
                'table_rows' => [
                    [
                        '1st Prize (1 Winner + Friend)',
                        'FREE · 1,000 QAR Value',
                        '1 winner gets our Full Makeover 100% FREE (Protein or Highlights + Facial + Nails). If a friend invited her, that friend ALSO wins a FREE 1,000 QAR Makeover!',
                    ],
                    [
                        '2nd Prize (Multiple Runners-Up)',
                        'Pay Only 500 QAR',
                        'Selected runners-up win a 500 QAR Voucher toward the 1,000 QAR Makeover—so they pay just 500 QAR for the full package (or get 100 QAR off any single service).',
                    ],
                ],
                'footer_note' => 'Ad Rule: Say "More than one person can win 2nd Prize" (never say everyone wins) so winners feel excited to book.',
            ],
            // Slide 3 (timeline — 4 steps)
            [
                'layout' => 'timeline',
                'eyebrow' => 'SLIDE 03 · 10-DAY PLAN',
                'highlight_metric' => '4 Simple Steps',
                'title' => 'How the Campaign Works (Step by Step)',
                'subtitle' => 'Everything from sign-up to salon booking in four clear steps.',
                'table_rows' => [
                    ['1. SIGN UP', 'Auto-Save to CRM', 'Client watches our 45s video and enters Name, Phone, Email & Branch—saved straight into Laleen CRM.'],
                    ['2. SHARE', 'Both Friends Win', 'She shares her WhatsApp invite link (+1 extra entry per friend, and both win FREE if her friend wins).'],
                    ['3. DAYS 1–7', '4 Traffic Sources', 'Promote via past CRM list, branch QR stands, "VIP" Reels DM, and Qatar ads (50% join in the last 2 days).'],
                    ['4. DAYS 8–10', '72-Hour Booking', 'Film the 1st-prize winner call, then message & call runners-up for 72h to book their 500 QAR Makeover.'],
                ],
                'footer_note' => 'On Day 11+, switch the page and ads to a regular 500 QAR Makeover Deal so daily bookings continue.',
            ],
            // Slide 4 (cards — 3 cards: Revenue & Growth Potential)
            [
                'layout' => 'cards',
                'eyebrow' => 'SLIDE 04 · REVENUE & GROWTH POTENTIAL',
                'highlight_metric' => 'Cash + Permanent CRM List',
                'title' => 'Why This Campaign Is Huge for Laleen',
                'subtitle' => 'Even if just 10% to 15% of runners-up claim their 500 QAR Makeover Voucher, look at what 10 days can bring:',
                'table_rows' => [
                    ['300 Sign-Ups · Safe Start', '15,000 QAR Cash', '30 bookings × 500 QAR + 300 local Phone Numbers & Emails saved in our CRM forever.'],
                    ['600 Sign-Ups · Target Goal', '30,000–45,000 QAR Cash', '60 to 90 bookings × 500 QAR across Old Airport & Al Wakrah + 600 new CRM contacts.'],
                    ['1,000+ Sign-Ups · Viral Hit', '75,000+ QAR Cash', '150 bookings × 500 QAR + chair upgrades + 1,000+ women we can message FREE on Eid & slow days.'],
                ],
                'footer_note' => 'Every Phone & Email stays in Laleen CRM permanently—giving us free repeat bookings without paying for ads twice.',
            ],
            // Slide 5 (table — Team Roles)
            [
                'layout' => 'table',
                'eyebrow' => 'SLIDE 05 · TEAM ROLES',
                'highlight_metric' => '7 Clear Roles',
                'title' => 'One Clear Job Per Team Member',
                'subtitle' => 'Everyone owns one clear number during the 10-day campaign.',
                'headers' => ['TEAM MEMBER', 'MAIN JOB', 'TARGET'],
                'rows' => [
                    ['Co-Owner (Sister)', 'Film 45s giveaway video & winner call', '1 shoot every 2 weeks'],
                    ['Ayan', 'Ads, sign-up page & Auto-CRM sync', '100% Phone + Email in CRM; <15 QAR/lead'],
                    ['Sana', 'Edit Reels & daily countdown Stories', '4–5 Reels + daily Stories'],
                    ['Zoya & Areeba', 'WhatsApp chat & book 500 QAR vouchers', '<5 min reply; 15%+ booked'],
                    ['Laden', 'Check follow-ups & call top inviters', '100% contacted in 72 hours'],
                    ['Ahana & Rhea', 'Old Airport & Al Wakrah salon care', '80%+ show-up; 35%+ upgrade'],
                    ['Arbab', 'Daily CRM task check & Friday sales report', 'Zero late tasks in CRM'],
                ],
                'footer_note' => 'Arbab checks CRM tasks daily and reviews total QAR booked vs. ad spend every Friday.',
            ],
            // Slide 6 (cover — Closing Next 3 Actions)
            [
                'layout' => 'cover',
                'eyebrow' => 'SLIDE 06 · NEXT 3 ACTIONS',
                'title' => '3 Simple Steps to Go Live',
                'subtitle' => '1) Confirm the 1,000 QAR Makeover services with Ahana & Rhea, 2) Film our Founder\'s 45-second video, and 3) Connect the Sign-Up Form (Phone + Email) to Laleen CRM and launch.',
                'highlight_metric' => 'Let\'s Hit 45,000+ QAR',
                'body_points' => [
                    '1) Confirm the 1,000 QAR Makeover services with Ahana & Rhea, 2) Film our Founder\'s 45-second video, and 3) Connect the Sign-Up Form (Phone + Email) to Laleen CRM and launch.',
                ],
                'table_rows' => [],
                'footer_note' => 'Laleen Beauty Salon · Old Airport & Al Wakrah',
            ],
        ];

        return [
            'title' => 'Laleen Beauty Salon — 1,000 QAR Makeover Giveaway Plan',
            'slug' => self::GIVEAWAY_SLUG,
            'category' => 'Growth Playbook',
            'summary' => 'Simple 6-slide team guide: how our 10-day giveaway builds our CRM Phone & Email list and can generate 15,000 to 75,000+ QAR in Makeover bookings.',
            'is_pinned' => true,
            'slides' => CrmPresentation::sanitizeSlides($rawSlides),
        ];
    }
}
