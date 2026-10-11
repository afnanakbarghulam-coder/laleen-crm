<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;

class KpiHubController extends Controller
{
    public function index()
    {
        return view('kpi.hub');
    }

    public function marketing()
    {
        return view('kpi.marketing');
    }

    public function customerService()
    {
        return view('kpi.customer-service');
    }

    public function salonStaff()
    {
        return view('kpi.salon-staff');
    }
}
