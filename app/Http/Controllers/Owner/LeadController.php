<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(): View
    {
        return view('owner.leads.index', ['leads' => Lead::latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('owner.leads.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Lead::create($this->validated($request));

        return redirect()->route('owner.leads.index')->with('success', 'Lead created.');
    }

    public function edit(Lead $lead): View
    {
        return view('owner.leads.edit', compact('lead'));
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $lead->update($this->validated($request));

        return redirect()->route('owner.leads.index')->with('success', 'Lead updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'], 'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:100'], 'service' => ['nullable', 'string', 'max:100'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', 'string'],
            'next_contact_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string'],
        ]);
    }
}
