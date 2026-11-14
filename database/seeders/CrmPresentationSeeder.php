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
     * Authoritative 12-slide "Laleen Beauty Salon — 1,000 QAR VIP Makeover Giveaway Plan (Old Airport & Al Wakrah)"
     * written in clear, team-friendly language across all 6 visual layouts (`cover`, `big_metric`, `timeline`,
     * `comparison`, `cards`, `table`) with Contact Number + Email -> Automatic Laleen CRM Database Sync.
     *
     * @return array<string, mixed>
     */
    public static function giveawayPlaybookAttributes(): array
    {
        $rawSlides = [
            // Slide 1 (cover)
            [
                'layout' => 'cover',
                'eyebrow' => 'TEAM CAMPAIGN GUIDE · LALEEN BEAUTY SALON',
                'title' => '1,000 QAR VIP Makeover Giveaway Plan',
                'subtitle' => 'A 7-day giveaway where clients submit their Contact Number & Email for a FREE 1,000 QAR Salon Makeover—automatically saving every lead into Laleen CRM—followed by 3 days of WhatsApp & Email follow-ups to turn entries into paid bookings.',
                'highlight_metric' => 'Old Airport & Al Wakrah',
                'body_points' => [
                    'A 7-day giveaway where clients submit their Contact Number & Email for a FREE 1,000 QAR Salon Makeover—automatically saving every lead into Laleen CRM—followed by 3 days of WhatsApp & Email follow-ups to turn entries into paid bookings.',
                ],
                'table_rows' => [],
                'footer_note' => 'Laleen Beauty Salon · Easy Step-by-Step Team Guide',
            ],
            // Slide 2 (big_metric)
            [
                'layout' => 'big_metric',
                'eyebrow' => 'SLIDE 02 · THE PRIZE & OFFER MATH',
                'highlight_metric' => '1,000 QAR',
                'title' => '1st Prize & 2nd Prize Breakdown',
                'body_points' => [
                    '1st Prize (FREE — 1,000 QAR Value): 1 lucky winner gets our Full Salon Makeover 100% FREE (Protein Hair Treatment or Highlights + Facial + Nails).',
                    '2nd Prize (500 QAR Voucher — Multiple Winners): Selected runners-up win a 500 QAR VIP Voucher off the 1,000 QAR Makeover—so they pay just 500 QAR to get the full package.',
                    'Important Ad Rule: In ads, say "More than one person can win 2nd Prize" (never say everyone wins) so clients feel excited to claim within 72 hours.',
                ],
                'table_rows' => [],
                'footer_note' => '20 clients claiming 2nd Prize = 10,000 QAR cash collected across both branches.',
            ],
            // Slide 3 (timeline 5-card)
            [
                'layout' => 'timeline',
                'eyebrow' => 'SLIDE 03 · 10-DAY STEP-BY-STEP SCHEDULE',
                'highlight_metric' => 'Day 0 -> Day 11+',
                'title' => '10-Day Campaign Timeline',
                'subtitle' => 'What our team does on each day—from launching the giveaway to turning entries into paid salon bookings.',
                'table_rows' => [
                    ['DAY 0', 'Set the Menu', 'Confirm the 1,000 QAR Makeover services (Protein/Highlights, Facial & Nails) at both branches.'],
                    ['DAY 1', 'Launch Page & CRM Sync', 'Publish Founder\'s video and sign-up form connected directly to Laleen CRM (capturing Phone + Email).'],
                    ['DAYS 1–7', 'Grow CRM Phone & Email List', 'Promote across 4 channels—every form submission is automatically added to our CRM database.'],
                    ['DAYS 8–10', 'Pick Winner & Close Bookings', 'Announce 1 FREE winner, then give runners-up 72 hours to claim their 500 QAR Makeover Voucher.'],
                    ['DAY 11+', 'Keep Running Regular Offer', 'Switch the page and ads into a regular 500 QAR Makeover Bundle so new bookings keep coming daily.'],
                ],
                'footer_note' => 'Start messaging runners-up immediately on Day 8 while excitement is still high.',
            ],
            // Slide 4 (comparison 2-box)
            [
                'layout' => 'comparison',
                'eyebrow' => 'SLIDE 04 · STEP 1: WHAT WINNERS GET',
                'highlight_metric' => '500 QAR Off -> Pay 500 QAR',
                'title' => '1st Prize vs. 2nd Prize Details',
                'subtitle' => '1st Prize gets women excited to enter and invite friends; 2nd Prize turns runners-up into paying bookings.',
                'table_rows' => [
                    [
                        '1st Place Full Makeover',
                        'FREE · 1,000 QAR Value',
                        '1 winner gets 1,000 QAR of services 100% FREE: Protein Hair Treatment or Highlights, Facial, Nails & Blowdry. Bonus: If a friend invited her, that friend wins the exact same makeover FREE!',
                    ],
                    [
                        '2nd Place VIP Voucher',
                        '500 QAR Voucher · Pay 500 QAR',
                        'Selected runners-up get a 500 QAR Voucher toward the 1,000 QAR Makeover—so they pay just 500 QAR for the full package (or use a 100 QAR voucher on any single service).',
                    ],
                ],
                'footer_note' => '2nd Prize bookings cover our ad costs and bring immediate cash flow to both branches.',
            ],
            // Slide 5 (cards 4-box — Sign-Up Form & Auto-CRM Capture)
            [
                'layout' => 'cards',
                'eyebrow' => 'SLIDE 05 · STEP 2: SIGN-UP FORM & AUTOMATIC CRM ENTRY',
                'highlight_metric' => 'Auto-Saved to CRM',
                'title' => 'Simple Sign-Up Form Connected to Our CRM',
                'subtitle' => 'Every person who submits the form is automatically saved into Laleen CRM with both her Contact Number and Email.',
                'table_rows' => [
                    ['01', '7-Day Timer at the Top', 'Live countdown clock at the very top so visitors sign up immediately instead of waiting.'],
                    ['02', '45-Second Founder Video', 'Short salon walkthrough video explaining the FREE 1,000 QAR Makeover and 500 QAR 2nd-Place Vouchers.'],
                    ['03', 'Contact Number, Email & Branch', 'Captures Name, Contact Number (WhatsApp), Email Address, and Branch (Old Airport or Al Wakrah).'],
                    ['04', 'Automatic CRM Database Sync', 'Every submission lands directly inside Laleen CRM—building our WhatsApp list AND Email list automatically.'],
                ],
                'footer_note' => 'Zero manual copy-pasting: every entry immediately builds our Old Airport & Al Wakrah CRM phone + email database.',
            ],
            // Slide 6 (big_metric)
            [
                'layout' => 'big_metric',
                'eyebrow' => 'SLIDE 06 · HOW CLIENTS BRING US FREE LEADS',
                'highlight_metric' => '2x Free Makeover',
                'title' => '"Invite a Friend, Both Win!" WhatsApp Rule',
                'subtitle' => 'Right after signing up, each client gets a personal WhatsApp invite link to share with her friends. Every friend who joins gives her +1 extra entry—and if her friend wins 1st Prize, BOTH of them win a FREE 1,000 QAR Makeover!',
                'body_points' => [
                    'Right after signing up, each client gets a personal WhatsApp invite link to share with her friends. Every friend who joins gives her +1 extra entry—and if her friend wins 1st Prize, BOTH of them win a FREE 1,000 QAR Makeover!',
                ],
                'table_rows' => [],
                'footer_note' => 'Clients who invite 2 or more friends get called first on Day 8 because they are our most excited buyers.',
            ],
            // Slide 7 (cards 3-box)
            [
                'layout' => 'cards',
                'eyebrow' => 'SLIDE 07 · STEP 3: WHAT TO EXPECT DURING THE 7 DAYS',
                'highlight_metric' => '30% · 20% · 50%',
                'title' => 'How Sign-Ups Come In Over 7 Days',
                'subtitle' => 'Don\'t panic if sign-ups slow down in the middle of the week—half of all leads join in the last 2 days.',
                'table_rows' => [
                    ['DAYS 1–2 · 30%', 'Strong Start', 'Fast early sign-ups from our past client WhatsApp message, salon reception QR codes, and launch video.'],
                    ['DAYS 3–5 · 20%', 'Quiet Middle Days', 'Sign-ups naturally slow down mid-week. Keep ads running steady and post fresh hair & skin transformation Reels.'],
                    ['DAYS 6–7 · 50%', 'Final 48-Hour Rush', '"Last chance to enter!" Stories and the countdown timer bring in half of our total leads right before closing.'],
                ],
                'footer_note' => 'Rule for marketing: Do NOT turn off working ads on Days 3–5—wait for the final 48-hour rush.',
            ],
            // Slide 8 (cards 4-box)
            [
                'layout' => 'cards',
                'eyebrow' => 'SLIDE 08 · STEP 3: WHERE OUR GIVEAWAY LEADS COME FROM',
                'highlight_metric' => '4 Lead Sources',
                'title' => '4 Ways We Promote the Giveaway',
                'subtitle' => 'Every channel sends women in Qatar to the exact same 1,000 QAR Makeover sign-up page.',
                'table_rows' => [
                    ['01', 'Past CRM Clients (WhatsApp & Email)', 'Send a Day-1 invite to our existing CRM phone and email list so loyal clients join and invite friends first.'],
                    ['02', 'Salon Reception QR Stands', 'Place a QR sign on the front desk at Old Airport and Al Wakrah so walk-in clients scan and enter on the spot.'],
                    ['03', 'Instagram Reels ("VIP" Comment)', 'When someone comments "VIP" on our Reels, she automatically gets a DM with the giveaway sign-up link.'],
                    ['04', 'Paid Instagram, Facebook & TikTok Ads', 'Run our Founder\'s 45-second giveaway video as a local Qatar ad to reach thousands of new women nearby.'],
                ],
                'footer_note' => 'Every new sign-up permanently grows our CRM Contact Number + Email list for future salon campaigns.',
            ],
            // Slide 9 (timeline 4-step)
            [
                'layout' => 'timeline',
                'eyebrow' => 'SLIDE 09 · STEP 4: HOW WE CLOSE BOOKINGS (DAYS 8–10)',
                'highlight_metric' => '3-Day Booking Window',
                'title' => '72-Hour Winner & WhatsApp Follow-Up Plan',
                'subtitle' => 'How our chat agents turn giveaway entries into 500 QAR Makeover bookings over 3 days.',
                'table_rows' => [
                    ['STEP 1', 'Film the Winner Call', 'Call the 1st Prize winner (and the friend who invited her) on speakerphone and film their happy reaction for Reels.'],
                    ['STEP 2', 'Send 500 QAR Vouchers (WhatsApp + Email)', 'Send selected runners-up their 500 QAR Makeover Voucher via WhatsApp and Email straight from our CRM list.'],
                    ['STEP 3', 'Call Top Referrers First', 'Phone-call clients who invited 2 or more friends first—they are the warmest leads most likely to book immediately.'],
                    ['STEP 4', 'Send 3 Countdown Reminders', 'Send friendly WhatsApp + Email reminders at 72h, 24h, and the final 4h before their 500 QAR voucher expires.'],
                ],
                'footer_note' => 'If a client still hesitates in the final 4 hours, offer the 100 QAR single-service voucher so we still win the booking.',
            ],
            // Slide 10 (comparison 2-box)
            [
                'layout' => 'comparison',
                'eyebrow' => 'SLIDE 10 · STEP 5: WHAT HAPPENS AFTER DAY 10 (DAY 11+)',
                'highlight_metric' => '500 QAR Makeover Bundle',
                'title' => 'Keep Bookings Coming After the Giveaway Ends',
                'subtitle' => 'Once the 10-day giveaway finishes, switch our page and ads to a regular salon bundle so new clients keep booking.',
                'table_rows' => [
                    [
                        'Days 8–10: Fast Cash From Vouchers',
                        'Days 8–10 · Voucher Bookings',
                        'Runners-up pay 500 QAR to claim their Makeover Package—bringing fast cash into both branches that pays for our ads.',
                    ],
                    [
                        'Day 11+: Switch to Regular Salon Offer',
                        'Day 11+ · Ongoing Monthly Deal',
                        'Switch ads to a regular 500 QAR Makeover Bundle AND re-message our newly grown CRM Phone + Email list anytime for free repeat bookings.',
                    ],
                ],
                'footer_note' => 'Use part of the cash collected on Days 8–10 to keep our best-performing Instagram and TikTok ads running.',
            ],
            // Slide 11 (table)
            [
                'layout' => 'table',
                'eyebrow' => 'SLIDE 11 · WHO DOES WHAT ON OUR TEAM',
                'highlight_metric' => '7 Team Roles',
                'title' => 'Team Roles & Weekly Targets',
                'subtitle' => 'Every team member has one clear job and target during the 10-day giveaway.',
                'headers' => ['TEAM MEMBER', 'MAIN JOB', 'TARGET GOAL'],
                'rows' => [
                    ['Co-Owner (Sister)', 'Giveaway Video & Winner Call', '1 filming session every 2 weeks'],
                    ['Ayan', 'Ads, Form-to-CRM Sync & Auto-DM', '100% form leads saved to CRM (Phone + Email); <15 QAR/lead'],
                    ['Sana', 'Video Editing & Daily Stories', '4–5 Reels per week + daily countdown Stories'],
                    ['Zoya & Areeba', 'WhatsApp Chat & Voucher Booking', 'Reply in under 5 mins; book 15%+ of 2nd-prize leads'],
                    ['Laden', 'Follow-Up Check & Priority Calls', '100% of leads contacted in 72h; call top inviters first'],
                    ['Ahana & Rhea', 'Old Airport & Al Wakrah Salon Floor', '80%+ client show-up; 35%+ upgrade at the chair'],
                    ['Arbab', 'CRM Task Check & Friday Sales Review', 'Zero late tasks in CRM + Friday ad sales check'],
                ],
                'footer_note' => 'Arbab checks CRM tasks daily and reviews total bookings vs. ad spend every Friday.',
            ],
            // Slide 12 (cover closing)
            [
                'layout' => 'cover',
                'eyebrow' => 'SLIDE 12 · FIRST 3 STEPS TO LAUNCH',
                'title' => 'Ready to Launch at Old Airport & Al Wakrah',
                'subtitle' => '1) Confirm the 1,000 QAR Makeover services with Ahana & Rhea, 2) Film our Founder\'s 45-second giveaway video, and 3) Assign all launch tasks inside the CRM Ops Tracker.',
                'highlight_metric' => 'Let\'s Fill Both Branches',
                'body_points' => [
                    '1) Confirm the 1,000 QAR Makeover services with Ahana & Rhea, 2) Film our Founder\'s 45-second giveaway video, and 3) Assign all launch tasks inside the CRM Ops Tracker.',
                ],
                'table_rows' => [],
                'footer_note' => 'Laleen Beauty Salon · Old Airport & Al Wakrah',
            ],
        ];

        return [
            'title' => 'Laleen Beauty Salon — 1,000 QAR VIP Makeover Giveaway Plan (Old Airport & Al Wakrah)',
            'slug' => self::GIVEAWAY_SLUG,
            'category' => 'Growth Playbook',
            'summary' => 'Easy 10-day team guide: clients enter their Contact Number & Email to win a FREE 1,000 QAR Salon Makeover—automatically building our CRM phone & email database—followed by 3 days of WhatsApp & Email follow-ups to close 500 QAR Makeover bookings.',
            'is_pinned' => true,
            'slides' => CrmPresentation::sanitizeSlides($rawSlides),
        ];
    }
}
