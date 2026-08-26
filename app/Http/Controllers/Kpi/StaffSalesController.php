<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiStaffSalesReport;
use Illuminate\Http\Request;

class StaffSalesController extends Controller
{
    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    public function index()
    {
        $reports = KpiStaffSalesReport::withCount('entries')
            ->orderByDesc('date_to')->orderByDesc('id')->paginate(15);

        return view('kpi.staff-sales.index', ['reports' => $reports, 'branches' => self::BRANCHES]);
    }

    public function create()
    {
        return view('kpi.staff-sales.create', ['branches' => self::BRANCHES]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch' => 'required|in:old_airport,wakrah',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'monthly_target_per_staff' => 'required|numeric|min:0',
            'staff' => 'required|array|min:1',
            'staff.*.name' => 'required|string|max:100',
            'staff.*.upsell' => 'required|numeric|min:0',
        ]);

        $report = KpiStaffSalesReport::create([
            'branch' => $validated['branch'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'monthly_target_per_staff' => $validated['monthly_target_per_staff'],
            'created_by' => auth()->id(),
        ]);

        foreach ($validated['staff'] as $staff) {
            $report->entries()->create([
                'staff_name' => $staff['name'],
                'total_upsell' => $staff['upsell'],
            ]);
        }

        return redirect()->route('kpi.staff-sales.show', $report)->with('success', 'Staff sales report saved.');
    }

    public function show(KpiStaffSalesReport $report)
    {
        $report->load('entries');

        return view('kpi.staff-sales.show', ['report' => $report, 'branches' => self::BRANCHES]);
    }

    public function destroy(KpiStaffSalesReport $report)
    {
        $report->delete();

        return redirect()->route('kpi.staff-sales.index')->with('success', 'Report deleted.');
    }
}
