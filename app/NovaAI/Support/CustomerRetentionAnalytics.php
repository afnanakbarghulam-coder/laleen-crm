<?php

namespace App\NovaAI\Support;

use App\Models\Appointment;
use App\Models\Sale;
use App\Support\ClientMaintenancePlanner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * NOVA READ-ONLY INTEGRATION RULE (see app/NovaAI/README.md): this class
 * only reads Appointment/Sale and consumes the CRM's existing, unmodified
 * App\Support\ClientMaintenancePlanner for maintenance/rebooking data. It
 * never writes anything and CustomerController/Customer/
 * ClientMaintenancePlanner never depend on it.
 *
 * "Qualifying visit" is defined independently here (not copied from
 * CustomerController::index()/show(), which count ANY appointment -
 * including cancelled, no-show, and future-scheduled ones - toward "last
 * visit"/LTV with no status or date filter at all): a qualifying visit is
 * an Appointment with a customer_id, a status NOT IN ('cancelled',
 * 'no_show'), and an appointment_datetime that has already happened. This
 * mirrors ClientMaintenancePlanner::buildSchedule()'s own filtering
 * convention (same status exclusion, same "not in the future" bound)
 * applied at the whole-appointment level instead of per (customer,
 * service) pair, since "did this person actually show up at all" is a
 * different question than "are they due for this specific service."
 *
 * Every method here returns aggregates or ID-keyed collections only -
 * never a customer name, phone, email, or other PII. See summary()'s own
 * docblock for exactly what leaves this class.
 */
class CustomerRetentionAnalytics
{
    /**
     * NOVA-DEFINED THRESHOLD - there is no established CRM concept of
     * "dormant customer" anywhere in the codebase (confirmed by search
     * before implementing this). 90 days with no qualifying visit is a
     * clearly-labelled Nova business rule, not a CRM-trusted calculation -
     * every place this appears in the snapshot says so explicitly.
     */
    public const DORMANT_THRESHOLD_DAYS = 90;

    public const TOP_MAINTENANCE_SERVICES_LIMIT = 5;

    /**
     * Aggregate-only executive summary. Returns:
     * - customers_with_visit_history, repeat_customers, repeat_rate_percent
     * - total_lifetime_spend, customers_with_spend, average_lifetime_spend
     * - repeat_customer_revenue, repeat_revenue_share_percent
     * - overdue_customers, due_soon_customers, upcoming_customers (distinct
     *   customer counts from ClientMaintenancePlanner, unmodified)
     * - top_maintenance_services (service name + overdue/due_soon counts,
     *   capped at TOP_MAINTENANCE_SERVICES_LIMIT)
     * - dormant_customers, potentially_reactivatable
     * - branch_distribution (counts per branch + mixed + insufficient_history)
     * No customer_id ever appears in the returned array - it is only used
     * internally as a grouping key.
     */
    public function summary(): array
    {
        $visitCounts = $this->qualifyingVisitCountsByCustomer();
        $lastVisitByCustomer = $this->lastQualifyingVisitByCustomer();
        $spendByCustomer = $this->lifetimeSpendByCustomer();

        $customersWithVisits = $visitCounts->count();
        $repeatCustomerIds = $visitCounts->filter(fn ($count) => $count >= 2)->keys()->all();
        $repeatCustomers = count($repeatCustomerIds);

        $totalLifetimeSpend = round($spendByCustomer->sum(), 2);
        $customersWithSpend = $spendByCustomer->count();
        $averageSpend = $customersWithSpend > 0 ? round($totalLifetimeSpend / $customersWithSpend, 2) : null;

        $repeatRevenue = round($spendByCustomer->only($repeatCustomerIds)->sum(), 2);
        $repeatRevenueShare = $totalLifetimeSpend > 0 ? round($repeatRevenue / $totalLifetimeSpend * 100, 1) : null;

        $dormantThreshold = now()->subDays(self::DORMANT_THRESHOLD_DAYS);
        $dormantIds = $lastVisitByCustomer->filter(fn (Carbon $date) => $date->lt($dormantThreshold))->keys()->all();
        $dormantCount = count($dormantIds);
        $reactivatableCount = $spendByCustomer->only($dormantIds)->filter(fn ($amount) => $amount > 0)->count();

        $schedule = (new ClientMaintenancePlanner())->buildSchedule();
        $overdueCustomers = $schedule->where('urgency', 'overdue')->pluck('customer_id')->unique()->count();
        $dueSoonCustomers = $schedule->where('urgency', 'due_soon')->pluck('customer_id')->unique()->count();
        $upcomingCustomers = $schedule->where('urgency', 'upcoming')->pluck('customer_id')->unique()->count();

        $topMaintenanceServices = $schedule
            ->whereIn('urgency', ['overdue', 'due_soon'])
            ->groupBy('service_name')
            ->map(fn (Collection $rows, $name) => [
                'service' => $name,
                'overdue' => $rows->where('urgency', 'overdue')->count(),
                'due_soon' => $rows->where('urgency', 'due_soon')->count(),
                'total' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->take(self::TOP_MAINTENANCE_SERVICES_LIMIT)
            ->values()
            ->all();

        return [
            'customers_with_visit_history' => $customersWithVisits,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate_percent' => $customersWithVisits > 0 ? round($repeatCustomers / $customersWithVisits * 100, 1) : null,
            'total_lifetime_spend' => $totalLifetimeSpend,
            'customers_with_spend' => $customersWithSpend,
            'average_lifetime_spend' => $averageSpend,
            'repeat_customer_revenue' => $repeatRevenue,
            'repeat_revenue_share_percent' => $repeatRevenueShare,
            'overdue_customers' => $overdueCustomers,
            'due_soon_customers' => $dueSoonCustomers,
            'upcoming_customers' => $upcomingCustomers,
            'top_maintenance_services' => $topMaintenanceServices,
            'dormant_customers' => $dormantCount,
            'potentially_reactivatable' => $reactivatableCount,
            'branch_distribution' => $this->branchDistribution(),
        ];
    }

    /** customer_id => number of qualifying visits (ever, all time). */
    private function qualifyingVisitCountsByCustomer(): Collection
    {
        return $this->qualifyingVisitsQuery()
            ->selectRaw('customer_id, COUNT(*) as visits')
            ->groupBy('customer_id')
            ->pluck('visits', 'customer_id');
    }

    /** customer_id => most recent qualifying visit datetime. */
    private function lastQualifyingVisitByCustomer(): Collection
    {
        return $this->qualifyingVisitsQuery()
            ->selectRaw('customer_id, MAX(appointment_datetime) as last_visit')
            ->groupBy('customer_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->customer_id => Carbon::parse($row->last_visit)]);
    }

    /** customer_id => lifetime revenue (SUM of every Sale tied to them - Sale has no status/void concept to filter on). */
    private function lifetimeSpendByCustomer(): Collection
    {
        return Sale::whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(total_amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->map(fn ($value) => (float) $value);
    }

    private function qualifyingVisitsQuery()
    {
        return Appointment::whereNotNull('customer_id')
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('appointment_datetime', '<=', now());
    }

    /**
     * Customers classified by which branch most of their qualifying visits
     * were at. A customer needs at least 2 qualifying visits to be
     * classified at all - one visit is never enough to call it a
     * "preference." A branch needs a strict majority of that customer's
     * visits to be counted as "primarily" that branch; otherwise the
     * customer is "mixed".
     */
    private function branchDistribution(): array
    {
        $byCustomer = $this->qualifyingVisitsQuery()
            ->selectRaw('customer_id, branch, COUNT(*) as visits')
            ->groupBy('customer_id', 'branch')
            ->get()
            ->groupBy('customer_id');

        $counts = [
            'old_airport' => 0,
            'wakrah' => 0,
            'home_service' => 0,
            'mixed' => 0,
            'insufficient_history' => 0,
        ];

        foreach ($byCustomer as $rows) {
            $total = $rows->sum('visits');

            if ($total < 2) {
                $counts['insufficient_history']++;
                continue;
            }

            $top = $rows->sortByDesc('visits')->first();

            if ($top->visits > $total / 2) {
                $counts[$top->branch] = ($counts[$top->branch] ?? 0) + 1;
            } else {
                $counts['mixed']++;
            }
        }

        return $counts;
    }
}
