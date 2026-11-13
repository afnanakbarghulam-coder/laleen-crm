---
name: laleen-minimal-ui
description: >-
  Luxury minimalist UI & copywriting standard for Laleen Beauty Salon CRM.
  Enforces breathable single-column layouts, borderless visual step tracks,
  dark espresso / rose-gold / champagne aesthetics, and the strict
  Anti-Nesting & Zero-Repetition Law across Blade views and operational hubs.
---

# Laleen Minimal UI Design Standard

Use this skill whenever designing, refactoring, or auditing Blade views, dashboards, modals, and operational guides inside Laleen CRM. Every screen should feel calm, editorial, and effortless for salon staff to scan at a glance.

---

## 1. Anti-Nesting & Zero-Repetition Law (Mandatory)

1. **Never Nest More Than 1 Level of Bordered Containers Inside a Card:**
   - Avoid "box-inside-box" overload (e.g., bordered diagram boxes + bordered step boxes + bordered green/red rule boxes + footer pills inside one card).
   - Inside a card container, separate content using **whitespace, borderless horizontal tracks (`1 ─── 2 ─── 3`), inline pills, and subtle `1px` hairline dividers** (`rgba(255, 255, 255, 0.06)`).
2. **Never Repeat the Same Action Twice on a Card (Zero-Repetition Rule):**
   - Never state the same instruction in both a **Step** and a **Do / Avoid guardrail**, or duplicate a visual diagram's steps in a second step list below it.
   - Steps explain **how to execute the workflow**; the guardrail line states **only net-new context, exceptions, or rules** not already in the steps.
3. **Never Display Developer, Meta, or URL Jargon in Production UI:**
   - Never expose meta phrases like `"Server-filtered view: Chat Agent"`, `"5-second visual playbooks, targets, and guardrails"`, or `"Role-Scoped Security Active"`.
   - Never print raw route paths (`(/kpis/agents)`, `(/leads)`, `(/appointments/calendar)`) in staff-facing copy. Staff click buttons—represent real UI controls as sleek inline pills (`<span class="g-ui-pill">+ Log Sign-In</span>`).

---

## 2. Layout & Navigation Architecture

- **Breathable Centered Canvas:** Prefer a clean centered container (`max-width: 1040px`) with generous vertical rhythm over cramped multi-sidebar layouts that squeeze main content.
- **Horizontal Category Pills Over Nested Sidebars:** When a page already sits inside the global CRM sidebar, use clean horizontal category pills right below the header rather than adding a second sticky left navigation rail.
- **Role-Accurate Stat Strips:** Only surface KPI target tiles that belong to the viewing role's actual day-to-day accountability (e.g., show Chat Agents their `13 / Day` Bookings, `≥ 85%` Chat Quality, and `≥ 30%` Team Lead Conversion—do not clutter their strip with Manager/Lead SLAs).

---

## 3. Clean 2-Zone Card Anatomy

Every operational or instructional card must contain **at most 2 clean zones** below its header:

1. **Header Row:**
   - Soft icon badge + clear title (≤ 5 words) + optional single subtle inline target chip (`Target: 13/day`) on the left, and the primary rose-gold action button (`Open Shift Log ↗`) on the right.
   - Do **not** stack redundant all-caps eyebrow labels above every card title, and do **not** append extra KPI/formula footer bars at the bottom of the card.
2. **Zone 1 — Borderless Horizontal Step Track (`1 ─── 2 ─── 3`):**
   - 3 to 4 columns on a clean open surface with numbered rose-gold circles connected by a subtle horizontal line (stacking cleanly on mobile).
   - Each step has a **2–3 word bold heading** + **1 crisp sentence (max 10 words)**.
   - Any auxiliary visual trail (priority pills, Mon–Tue open/locked pills, or 2-lane source tags) renders **inline and borderless** without an extra wrapper box.
3. **Zone 2 — Compact Single-Line Guardrail Footer (`✓` · `✕`):**
   - Separated only by a subtle top hairline border (`border-top: 1px solid rgba(255, 255, 255, 0.06)`).
   - Left side: **1 concise emerald `✓` rule**. Right side: **1 concise ruby `✕` rule** (inline text, zero heavy tinted box backgrounds).
4. **Uniform 3-Column Card Rhythm:**
   - Never render vertical 5-step multi-paragraph cards or 3-column Q&A footers. Any workflow longer than 3 steps must be split into two uniform horizontal `1 ── 2 ── 3` cards with single-line `✓ / ✕` footers.

---

## 4. Palette & Surface Tokens

- **Card Surface:** `linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%)` with `1px solid rgba(229, 184, 168, 0.14)` border and `16px` radius.
- **Accents:** Rose-Gold (`#e5b8a8` / `#d98f83`), Champagne (`#d4a373`), Warm Cream (`#f8efe6`), Muted Taupe (`#b8a29a`), Emerald (`#6ee7b7`), Ruby (`#fda4af`).
- **Print Readiness:** Always include `@media print` overrides switching surfaces to clean white (`#ffffff`) and text to crisp ink (`#111111`) while hiding navigation controls.
