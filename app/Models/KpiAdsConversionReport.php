<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiAdsConversionReport extends Model
{
    const TARGET_CONVERSION = 20.0;

    protected $fillable = [
        'date_from',
        'date_to',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Category breakdown — leads, bookings, avg ticket, conversion %, revenue,
     * target %, and status — computed entirely from Ad Lead Data Entry rows
     * logged within this report's date range. There is no manual input: this
     * report is just a saved bookmark for a date range, always recomputed
     * fresh from the raw lead log.
     */
    public function computedCategories(): array
    {
        return AdLeadEntry::whereBetween('date', $this->dateRangeBounds())
            ->get()
            ->groupBy('category')
            ->map(function ($entries, $name) {
                $leads = $entries->count();
                $booked = $entries->filter(fn ($e) => $e->isBooked());
                $bookings = $booked->count();
                $revenue = (float) $booked->sum('ticket_amount');
                $avgTicket = $bookings > 0 ? round($revenue / $bookings, 2) : 0.0;

                $conversion = $leads > 0 ? round($bookings / $leads * 100, 1) : 0.0;
                $pctOfTarget = round($conversion / self::TARGET_CONVERSION * 100, 1);

                return [
                    'name' => $name,
                    'leads' => $leads,
                    'bookings' => $bookings,
                    'avg_ticket' => $avgTicket,
                    'conversion' => $conversion,
                    'revenue' => round($revenue, 2),
                    'pct_of_target' => $pctOfTarget,
                    'status' => $this->statusFor($conversion),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Bookings/revenue for one branch within this report's date range,
     * computed from Ad Lead Data Entry rows.
     */
    private function branchStats(string $branch): array
    {
        $booked = AdLeadEntry::whereBetween('date', $this->dateRangeBounds())
            ->where('branch', $branch)
            ->get();

        return [
            'bookings' => $booked->count(),
            'revenue' => (float) $booked->sum('ticket_amount'),
        ];
    }

    /**
     * Start-of-day / end-of-day bounds for date-range queries. AdLeadEntry's
     * `date` column is stored as a full "Y-m-d H:i:s" string regardless of
     * column type (SQLite has no distinct DATE storage format), so a plain
     * 'Y-m-d' bound would lexically exclude every row — spanning the whole
     * day on both ends keeps the comparison correct everywhere.
     */
    private function dateRangeBounds(): array
    {
        return [$this->date_from->copy()->startOfDay(), $this->date_to->copy()->endOfDay()];
    }

    public function getOldAirportBookingsAttribute()
    {
        return $this->branchStats('old_airport')['bookings'];
    }

    public function getOldAirportRevenueAttribute()
    {
        return $this->branchStats('old_airport')['revenue'];
    }

    public function getWakrahBookingsAttribute()
    {
        return $this->branchStats('wakrah')['bookings'];
    }

    public function getWakrahRevenueAttribute()
    {
        return $this->branchStats('wakrah')['revenue'];
    }

    public function statusFor(float $conversion): string
    {
        return match (true) {
            $conversion >= 20 => 'Above',
            $conversion >= 15 => 'Near',
            $conversion >= 10 => 'Below',
            default => 'Critical',
        };
    }

    /**
     * Adaptive status badge for a computed category: strong performers show
     * how many times the target they're converting at (e.g. "2.3x Target"),
     * everyone else shows their plain status label.
     */
    public function categoryBadge(array $category): array
    {
        $status = $category['status'];
        $multiple = self::TARGET_CONVERSION > 0 ? round($category['conversion'] / self::TARGET_CONVERSION, 1) : 0.0;

        if ($status === 'Above' && $multiple >= 2.0) {
            return ['label' => $multiple . 'x Target', 'class' => 'kpi-badge-green'];
        }

        return match ($status) {
            'Above' => ['label' => 'Above Target', 'class' => 'kpi-badge-green'],
            'Near' => ['label' => 'Near Target', 'class' => 'kpi-badge-amber'],
            'Below' => ['label' => 'Below', 'class' => 'kpi-badge-amber'],
            default => ['label' => 'Critical', 'class' => 'kpi-badge-red'],
        };
    }

    /**
     * Side-by-side branch comparison with booking/revenue share and the gap
     * between the two branches, for this report's date range.
     */
    public function branchComparison(): array
    {
        $oldAirport = ['bookings' => (int) $this->old_airport_bookings, 'revenue' => (float) $this->old_airport_revenue];
        $wakrah = ['bookings' => (int) $this->wakrah_bookings, 'revenue' => (float) $this->wakrah_revenue];

        $totalBookings = $oldAirport['bookings'] + $wakrah['bookings'];
        $totalRevenue = $oldAirport['revenue'] + $wakrah['revenue'];

        $oldAirport['booking_share'] = $totalBookings > 0 ? round($oldAirport['bookings'] / $totalBookings * 100, 1) : 0.0;
        $wakrah['booking_share'] = $totalBookings > 0 ? round($wakrah['bookings'] / $totalBookings * 100, 1) : 0.0;
        $oldAirport['revenue_share'] = $totalRevenue > 0 ? round($oldAirport['revenue'] / $totalRevenue * 100, 1) : 0.0;
        $wakrah['revenue_share'] = $totalRevenue > 0 ? round($wakrah['revenue'] / $totalRevenue * 100, 1) : 0.0;

        $bookingGap = $oldAirport['bookings'] - $wakrah['bookings'];
        $revenueGap = $oldAirport['revenue'] - $wakrah['revenue'];

        return [
            'old_airport' => $oldAirport,
            'wakrah' => $wakrah,
            'total_bookings' => $totalBookings,
            'total_revenue' => round($totalRevenue, 2),
            'booking_gap' => abs($bookingGap),
            'revenue_gap' => round(abs($revenueGap), 2),
            'leading_branch' => match (true) {
                $bookingGap > 0 => 'Old Airport',
                $bookingGap < 0 => 'Al Wakrah',
                $revenueGap > 0 => 'Old Airport',
                $revenueGap < 0 => 'Al Wakrah',
                default => null,
            },
        ];
    }

    /**
     * Rule-based Scale/Fix/Pause recommendation per ad category, based on
     * conversion vs. the 20% target and booking volume. Purely mechanical —
     * no manual input, recomputed fresh every time from computedCategories().
     */
    public function actionableInsights(): array
    {
        return array_map(function ($category) {
            $leads = $category['leads'];
            $bookings = $category['bookings'];
            $conversion = $category['conversion'];
            $multiple = self::TARGET_CONVERSION > 0 ? round($conversion / self::TARGET_CONVERSION, 1) : 0.0;
            $leadWord = $leads === 1 ? 'lead' : 'leads';

            $action = match ($category['status']) {
                'Above' => 'scale',
                'Near', 'Below' => 'fix',
                default => 'pause',
            };

            $reason = match (true) {
                $leads === 0 => 'No leads logged for this category in the selected period.',
                $bookings === 0 => "Zero bookings from {$leads} {$leadWord} — pause spend and review targeting or offer fit before trying again.",
                $action === 'scale' => "Converting at {$multiple}x the 20% target — increase budget and expand this campaign.",
                $action === 'fix' => "Below the 20% target ({$conversion}%) — tighten messaging, speed up follow-up, or refine targeting before scaling spend.",
                default => "Conversion is critical ({$conversion}%) — pause spend and review lead quality, response time, and offer fit.",
            };

            return [
                'name' => $category['name'],
                'action' => $action,
                'conversion' => $conversion,
                'multiple' => $multiple,
                'reason' => $reason,
                'low_sample' => $leads > 0 && $leads < 5,
            ];
        }, $this->computedCategories());
    }

    public function statusRecommendation(string $status): string
    {
        return match ($status) {
            'Above' => 'Performing well — maintain current messaging and follow-up cadence.',
            'Near' => 'Close to target — fine-tune offer messaging and speed up follow-ups.',
            'Below' => 'Increase follow-up frequency and review lead quality for this category.',
            default => 'Immediate review needed — audit lead source, response time, and offer fit.',
        };
    }

    public function totals(): array
    {
        $categories = $this->computedCategories();
        $totalLeads = array_sum(array_column($categories, 'leads'));
        $totalBookings = array_sum(array_column($categories, 'bookings'));
        $totalRevenue = array_sum(array_column($categories, 'revenue'));
        $overallConversion = $totalLeads > 0 ? round($totalBookings / $totalLeads * 100, 1) : 0.0;

        return [
            'total_leads' => $totalLeads,
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'overall_conversion' => $overallConversion,
            'overall_met_target' => $overallConversion >= self::TARGET_CONVERSION,
        ];
    }
}
