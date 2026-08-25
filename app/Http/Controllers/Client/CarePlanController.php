<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CarePlan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarePlanController extends Controller
{
    public function index(Request $request): View
    {
        return view('client.care-plans.index', ['plans' => CarePlan::with('project')->where('company_id', $request->user()->company_id)->latest()->get()]);
    }

    public function show(Request $request, CarePlan $carePlan): View
    {
        $this->authorize('view', $carePlan);

        return view('client.care-plans.show', ['plan' => $carePlan->load(['project', 'activities', 'invoices' => fn ($query) => $query->where('status', '!=', 'draft')->latest()])]);
    }
}
