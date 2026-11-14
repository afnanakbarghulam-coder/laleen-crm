---
name: laleen-deck-builder
description: >-
  Team-friendly presentation & strategy deck architect for Laleen Beauty Salon CRM.
  Transforms raw campaign notes, SOP drafts, or growth ideas into clear, simple
  8–12 slide decks across 6 visual layouts (cover, big_metric, timeline,
  comparison, cards, table) for 1-click JSON import into CRM Guides ->
  Presentations or direct local database seeding—with zero external API bloat.
---

# Laleen Deck Builder Skill

Use this skill whenever the user asks to turn raw notes, WhatsApp brainstorms, marketing plans, SOPs, or Laleen Rituals guides into an in-app presentation deck for **`CRM Guides -> Presentations`**.

The Presentations engine runs with **zero external LLM calls inside Laravel**. You act as the deck strategist: synthesize raw text into a clear, easy-to-follow 8–12 slide JSON deck that either pastes directly into **`+ New Presentation -> Paste Deck JSON (Instant Import)`** on live production or seeds directly into `crm_presentations` locally.

---

## 1. Plain Team Language, Roster & Density Rules (Mandatory)

1. **Plain, Team-Friendly Language by Default (Zero Agency Jargon):**
   - Write in **simple, friendly everyday English** that every chat agent, branch manager, video editor, and stylist can understand in 5 seconds.
   - Unless the user explicitly asks for marketing-agency terminology, **avoid heavy jargon** like *"self-liquidating ad spend"*, *"omnichannel VSL"*, *"CPL arbitrage"*, or *"ROAS"*. Instead say *"short 45-second Founder video"*, *"under 15 QAR per lead"*, *"bookings that pay for our ads"*, and *"Friday ad sales check"*.
2. **Current Laleen Team Roster (Use Accurate Names & Roles):**
   - **Co-Owner (Sister):** Giveaway/campaign videos & winner reaction calls.
   - **Ayan:** Paid ads (Instagram, Facebook, TikTok), sign-up pages & "VIP" Auto-DM setup.
   - **Sana:** Reels editing & daily countdown/transformation Stories.
   - **Zoya & Areeba:** WhatsApp chat agents & voucher/appointment booking (`< 5 min` reply).
   - **Laden:** Follow-up lead sweep & priority phone calls to top referrers.
   - **Ahana & Rhea:** Branch floor leads across **Old Airport** (`Ahana`) & **Al Wakrah** (`Rhea`) (`80%+` show-up, `35%+` chair upgrades).
   - **Arbab:** Daily CRM Ops Tracker checks & Friday sales vs. ad spend review.
   - *(Never use outdated names like `Faye`—always use `Ahana` for Old Airport.)*
3. **8–12 Slide Arc & Dynamic Layout Variety:**
   - Default to **8 to 12 slides** unless the user specifies an exact slide count or per-slide layout plan:
     - **Slide 1 (`cover`):** Clear headline, team/branch eyebrow, and 1-sentence summary.
     - **Slide 2 (`big_metric`):** Hero QAR offer, prize value, or target goal (`1,000 QAR`, `500 QAR`, `< 5 Mins`) + concise breakdown.
     - **Middle Slides (`timeline`, `comparison`, `cards`):** Day-by-day schedule, offer comparison, lead channels, and WhatsApp follow-up steps.
     - **Team Slide (`table`):** Who does what (`TEAM MEMBER | MAIN JOB | TARGET GOAL`).
     - **Closing Slide (`cover` or `cards`):** First 3 simple steps to launch.
   - Mix all 6 visual layouts (`cover`, `big_metric`, `timeline`, `comparison`, `cards`, `table`) dynamically so the deck stays visually engaging.
4. **Concise Scannability & Doha Context:**
   - Keep slides clean and scannable—short headings (**3–7 words**) and crisp 1-sentence descriptions on cards and timeline steps.
   - Always use **QAR** currency (`1,000 QAR`, `500 QAR`), **Old Airport** & **Al Wakrah** branch names, and **Laleen Rituals** product terminology where relevant.
   - Never print raw developer route paths (`(/leads)`, `(/guide)`) on slides.
5. **Strict Zero-Compensation & Zero-PIN Privacy Law:**
   - **NEVER** include staff salaries (`base_salary`), hourly wages (`hourly_wage`), contract compensation figures, or PIN codes (`contract_pin`, `pin_hash`) anywhere in a deck. Both you and the Laravel JSON importer strictly reject confidential HR/payroll fields (`422`).

---

## 2. Authoritative `CrmPresentation` JSON Schema

The **`Paste Deck JSON (Instant Import)`** endpoint (`POST /guide/presentations/import`) validates and sanitizes the following exact JSON object:

```json
{
  "title": "Deck Title (max 255 chars, required)",
  "slug": "optional-kebab-slug",
  "category": "Growth Playbook | Marketing Strategy | Branch SOP | Laleen Rituals",
  "summary": "One-sentence team-friendly summary shown on the vault card (max 1000 chars).",
  "is_pinned": false,
  "slides": [
    {
      "layout": "cover | big_metric | timeline | comparison | cards | table",
      "eyebrow": "SHORT UPPERCASE KICKER",
      "title": "Slide Headline",
      "subtitle": "Clear 1-line explanation or takeaway.",
      "highlight_metric": "Optional badge or hero figure (e.g., 1,000 QAR)",
      "body_points": [
        "Optional bullet point (supports 1 takeaway or 2–3 clear points on big_metric)."
      ],
      "table_rows": [],
      "headers": [],
      "rows": [],
      "footer_note": "Optional single-line team tip or rule at the bottom of the slide."
    }
  ]
}
```

### Allowed `category` Values (Exact Match Required)
- `"Growth Playbook"`
- `"Marketing Strategy"`
- `"Branch SOP"`
- `"Laleen Rituals"`

---

## 3. Per-Layout Field Blueprints

Use the exact structure below for each of the 6 supported `layout` types:

### 1. `cover` — Playfair Serif Headline & Subtitle
Best for Slide 1 (Opening Cover) and Final Slide (First 3 Steps to Launch).
```json
{
  "layout": "cover",
  "eyebrow": "TEAM CAMPAIGN GUIDE · LALEEN BEAUTY SALON",
  "title": "1,000 QAR VIP Makeover Giveaway Plan",
  "subtitle": "A 7-day giveaway for a FREE 1,000 QAR Salon Makeover followed by 3 days of WhatsApp follow-ups.",
  "highlight_metric": "Old Airport & Al Wakrah",
  "footer_note": "Laleen Beauty Salon · Easy Step-by-Step Team Guide"
}
```

### 2. `big_metric` — Oversized Hero Figure & Breakdown
Best for highlighting the main QAR offer, prize math, or viral referral rule (`body_points` supports either a single takeaway sentence or 2–3 concise bullet points).
```json
{
  "layout": "big_metric",
  "eyebrow": "THE PRIZE & OFFER MATH",
  "title": "1st Prize & 2nd Prize Breakdown",
  "highlight_metric": "1,000 QAR",
  "body_points": [
    "1st Prize (FREE — 1,000 QAR Value): 1 lucky winner gets our Full Salon Makeover 100% FREE.",
    "2nd Prize (500 QAR Voucher): Selected runners-up pay just 500 QAR for the full 1,000 QAR package."
  ],
  "footer_note": "20 clients claiming 2nd Prize = 10,000 QAR cash collected across both branches."
}
```

### 3. `timeline` — 3 to 5 Horizontal Step Cards
Pass **3 to 5 rows** in `table_rows` formatted as `["Day / Step Badge", "Short Step Title", "Simple 1-sentence description"]`.
```json
{
  "layout": "timeline",
  "eyebrow": "HOW WE CLOSE BOOKINGS",
  "title": "4-Step WhatsApp Booking Flow",
  "subtitle": "How our chat agents turn new leads into confirmed salon visits.",
  "highlight_metric": "3-Day Booking Window",
  "table_rows": [
    ["STEP 1", "Reply Under 5 Mins", "Greet the client by name and confirm Old Airport or Al Wakrah."],
    ["STEP 2", "Share 500 QAR Voucher", "Explain everything included in the 1,000 QAR Makeover package."],
    ["STEP 3", "Call Top Inviters", "Phone-call leads who invited 2+ friends first while excitement is high."],
    ["STEP 4", "Lock Chair Time", "Book her preferred date and send instant WhatsApp confirmation."]
  ],
  "footer_note": "Offer the 100 QAR single-service voucher if a client hesitates in the final 4 hours."
}
```

### 4. `comparison` — 2-Column Side-by-Side Boxes
Pass **2 rows** in `table_rows` formatted as `["Box Title", "Top Price / Value Badge", "Clear explanation of the option"]`.
```json
{
  "layout": "comparison",
  "eyebrow": "WHAT WINNERS GET",
  "title": "1st Prize vs. 2nd Prize Details",
  "subtitle": "1st Prize gets women excited to join; 2nd Prize turns runners-up into paid bookings.",
  "highlight_metric": "500 QAR Off -> Pay 500 QAR",
  "table_rows": [
    ["1st Place Full Makeover", "FREE · 1,000 QAR Value", "1 winner (plus the friend who invited her) gets Protein/Highlights, Facial, Nails & Blowdry 100% FREE."],
    ["2nd Place VIP Voucher", "500 QAR Voucher · Pay 500 QAR", "Selected runners-up pay just 500 QAR for the full 1,000 QAR Makeover (or use a 100 QAR single-service voucher)."]
  ],
  "footer_note": "2nd Prize bookings cover our ad costs and bring immediate cash flow to both branches."
}
```

### 5. `cards` — 3 or 4 Clean Grid Boxes
Pass **3 or 4 rows** in `table_rows` formatted as `["01 (or Badge)", "Card Title", "Simple explanation"]`.
```json
{
  "layout": "cards",
  "eyebrow": "WHERE LEADS COME FROM",
  "title": "4 Ways We Promote the Giveaway",
  "subtitle": "Every channel sends women in Qatar to the exact same sign-up page.",
  "highlight_metric": "4 Lead Sources",
  "table_rows": [
    ["01", "Past Salon Clients (WhatsApp)", "Day-1 WhatsApp invite to past Laleen guests so loyal clients join first."],
    ["02", "Salon Reception QR Stands", "QR sign at Old Airport and Al Wakrah desks so walk-ins scan on the spot."],
    ["03", "Instagram Reels (\"VIP\" Comment)", "Commenting \"VIP\" on Reels automatically sends the sign-up link by DM."],
    ["04", "Paid Social Ads", "Founder's 45-second video running on Instagram, Facebook & TikTok in Qatar."]
  ],
  "footer_note": "Mixing free WhatsApp & QR sign-ups with paid ads keeps our cost per lead low."
}
```

### 6. `table` — Team Roles & Weekly Targets Table
Pass column names in `headers` and team rows in `rows`.
```json
{
  "layout": "table",
  "eyebrow": "WHO DOES WHAT ON OUR TEAM",
  "title": "Team Roles & Weekly Targets",
  "subtitle": "Every team member has one clear job and target.",
  "highlight_metric": "7 Team Roles",
  "headers": ["TEAM MEMBER", "MAIN JOB", "TARGET GOAL"],
  "rows": [
    ["Co-Owner (Sister)", "Giveaway Video & Winner Call", "1 filming session every 2 weeks"],
    ["Ayan", "Ads, Sign-Up Page & Auto-DM", "Under 15 QAR per lead; ads & \"VIP\" DM running"],
    ["Sana", "Video Editing & Daily Stories", "4–5 Reels per week + daily countdown Stories"],
    ["Zoya & Areeba", "WhatsApp Chat & Voucher Booking", "Reply in under 5 mins; book 15%+ of 2nd-prize leads"],
    ["Laden", "Follow-Up Check & Priority Calls", "100% of leads contacted in 72h; call top inviters first"],
    ["Ahana & Rhea", "Old Airport & Al Wakrah Salon Floor", "80%+ client show-up; 35%+ upgrade at the chair"],
    ["Arbab", "CRM Task Check & Friday Sales Review", "Zero late tasks in CRM + Friday ad sales check"]
  ],
  "footer_note": "Arbab checks CRM tasks daily and reviews total bookings vs. ad spend every Friday."
}
```

---

## 4. Output Modes

Choose the mode that matches the user's request:

### Mode A — Ready-to-Paste JSON Block (Default / Live Production Import)
When the user gives you raw notes or asks to generate a deck they can paste into the live CRM:
1. Output a brief 2–3 line summary of the slide plan in plain English.
2. Provide **one clean, valid `json` code block** strictly matching the schema above (no trailing commas, no comments inside JSON).
3. Remind the user they can import it in 1 click via **`CRM Guides -> Presentations -> + New Presentation -> Paste Deck JSON (Instant Import)`**.

### Mode B — Direct Local Database Seeding (When Asked to Save / Publish Locally)
When the user explicitly asks to add, save, or seed the presentation directly into the workspace CRM database:
1. Build the slide array using `CrmPresentation::sanitizeSlides($slides)`.
2. Upsert idempotently via `App\Models\CrmPresentation::updateOrCreate(['slug' => $slug], [...])` (and update/run the corresponding Laravel Seeder or migration if the deck ships in git).
3. Confirm the saved deck title, slide count, and layout sequence so the user can open **`CRM Guides -> Presentations`** immediately.
