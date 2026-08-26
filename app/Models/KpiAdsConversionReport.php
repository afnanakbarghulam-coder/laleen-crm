<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiAdsConversionReport extends Model
{
    const TARGET_CONVERSION = 20.0;

    protected $fillable = [
        'date_from',
        'date_to',
        'categories',
        'old_airport_bookings',
        'old_airport_revenue',
        'wakrah_bookings',
        'wakrah_revenue',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'categories' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Categories enriched with conversion %, revenue, target %, and status.
     */
    public function computedCategories(): array
    {
        return array_map(function ($category) {
            $leads = (int) ($category['leads'] ?? 0);
            $bookings = (int) ($category['bookings'] ?? 0);
            $avgTicket = (float) ($category['avg_ticket'] ?? 0);

            $conversion = $leads > 0 ? round($bookings / $leads * 100, 1) : 0.0;
            $revenue = round($bookings * $avgTicket, 2);
            $pctOfTarget = round($conversion / self::TARGET_CONVERSION * 100, 1);

            return [
                'name' => $category['name'] ?? 'Untitled',
                'leads' => $leads,
                'bookings' => $bookings,
                'avg_ticket' => $avgTicket,
                'conversion' => $conversion,
                'revenue' => $revenue,
                'pct_of_target' => $pctOfTarget,
                'status' => $this->statusFor($conversion),
            ];
        }, $this->categories ?? []);
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
