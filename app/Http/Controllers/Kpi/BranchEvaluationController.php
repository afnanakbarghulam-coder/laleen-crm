<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;

class BranchEvaluationController extends Controller
{
    /**
     * Static mystery-shopper report, built to nail the UI before this is
     * backed by real submitted visits.
     */
    public function index()
    {
        return view('kpi.branch-evaluations.show');
    }
}
