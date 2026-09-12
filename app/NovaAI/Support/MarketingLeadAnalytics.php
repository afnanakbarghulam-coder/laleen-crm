<?php

namespace App\NovaAI\Support;

use App\Models\Lead;
use App\Models\KpiAdsConversionReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * NOVA READ-ONLY INTEGRATION RULE (see app/NovaAI/README.md): ad-inquiry
 * figures come from a fresh, unsaved App\Models\KpiAdsConversionReport
 * instance's own totals()/computedCategories()/branchComparison() -
 * exactly how UserController::dashboard() already uses this class.
 * ::create()/save() is never called, so no row is ever written to
 * kpi_ads_conversion_reports. Lead follow-up figures read App\Models\Lead
 * directly; the overdue count mirrors (does not call, since it is
 * private) LeadController::overdueLeadsQuery()'s exact where-clauses.
 *
 * DATA QUALITY - read this before trusting any number from this class:
 * - AdLeadEntry rows are 100% manually typed by staff, one at a time.
 *   There is no ad-platform integration and no advertising-spend field
 *   anywhere in this schema - CAC, cost-per-lead, and ROAS are
 *   structurally impossible to calculate from any current CRM source.
 * - AdLeadEntry::isBooked() means "a branch was manually selected on this
 *   row" - it is a manual marker, not a link to a real Appointment or
 *   Sale record. There is no FK from AdLeadEntry to Customer, Appointment,
 *   or Sale at all.
 * - AdLeadEntry.ticket_amount is self-reported by whoever logged the
 *   entry and is never reconciled against actual Sale rows - it must
 *   never be called "verified revenue," only "reported booking value."
 * - Only entries with a branch selected have a branch at all (branch is
 *   never set on an unbooked lead), so a branch's *inquiry* volume or
 *   conversion rate cannot be computed - only its *booked* count/value.
 *   Home Service is not a supported branch value on AdLeadEntry at all.
 * - Lead.needful_done means "the required follow-up task was completed,"
 *   never "the lead converted to a booking or sale." Lead.category values
 *   'no_show'/'cancel' are set automatically from the Enhanced Calendar,
 *   never chosen by staff.
 */
class MarketingLeadAnalytics
{
    public const TOP_CATEGORIES_LIMIT = 5;

    /** Ad-inquiry log summary for [$from, $to] - all figures as defined in this class's own docblock above. */
    public function adSummary(Carbon $from, Carbon $to): array
    {
        $report = new KpiAdsConversionReport(['date_from' => $from, 'date_to' => $to]);

        $categories = collect($report->computedCategories())
            ->sortByDesc('leads')
            ->take(self::TOP_CATEGORIES_LIMIT)
            ->values()
            ->all();

        return [
            'totals' => $report->totals(),
            'top_categories' => $categories,
            'branch_comparison' => $report->branchComparison(),
        ];
    }

    /** Lead follow-up log summary - category counts are period-scoped, overdue is all-time (mirroring LeadController::analytics()'s own convention exactly). */
    public function leadSummary(Carbon $from, Carbon $to): array
    {
        $leads = Lead::whereBetween('created_at', [$from, $to])->get();

        $categoryCounts = collect(Lead::CATEGORIES)
            ->mapWithKeys(fn ($label, $key) => [$label => $leads->where('category', $key)->count()])
            ->all();

        return [
            'total_leads' => $leads->count(),
            'category_counts' => $categoryCounts,
            'follow_up_completed' => $leads->where('needful_done', 'yes')->count(),
            'follow_up_pending' => $leads->whereIn('needful_done', [null, 'no'])->count(),
            'overdue_all_time' => $this->overdueCount(),
        ];
    }

    /**
     * Mirrors LeadController::overdueLeadsQuery() exactly (that method is
     * private, so this reproduces rather than calls it): a follow-up date
     * in the past, not marked done, and not a dead/cancelled lead. This is
     * intentionally all-time, not scoped to [$from, $to] - matching how
     * LeadController::analytics() itself computes this figure.
     */
    private function overdueCount(): int
    {
        return Lead::whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '<', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('needful_done')->orWhere('needful_done', '!=', 'yes'))
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', 'cancel'))
            ->count();
    }
}
