<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CarePlan;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarePlanController extends Controller
{
    public function index(): View
    {
        return view('owner.care-plans.index', ['plans' => CarePlan::with(['company', 'project'])->latest()->paginate(25)]);
    }

    public function create(): View
    {
        return view('owner.care-plans.create', ['companies' => Company::orderBy('name')->get(), 'projects' => Project::with('company')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $project = isset($data['project_id']) ? Project::findOrFail($data['project_id']) : null;
        abort_if($project && $project->company_id !== (int) $data['company_id'], 422, 'Project does not belong to company.');
        $data['included_services'] = $this->services($request->input('included_services_text'));
        $plan = CarePlan::create($data);

        return redirect()->route('owner.care-plans.show', $plan)->with('success', 'Care & Support plan created.');
    }

    public function show(CarePlan $carePlan): View
    {
        return view('owner.care-plans.show', ['plan' => $carePlan->load(['company', 'project', 'activities.recorder', 'invoices'])]);
    }

    public function update(Request $request, CarePlan $carePlan): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'active', 'paused', 'cancelled', 'expired'])],
            'next_billing_date' => ['nullable', 'date'], 'ssl_status' => ['required', 'string', 'max:100'],
            'service_status' => ['required', 'string', 'max:100'], 'last_backup_at' => ['nullable', 'date'],
            'last_maintenance_at' => ['nullable', 'date'],
        ]);
        $carePlan->update($data);

        return back()->with('success', 'Care plan status updated.');
    }

    public function activity(Request $request, CarePlan $carePlan): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:support,maintenance,backup,monitoring,other'],
            'minutes' => ['required', 'integer', 'min:0', 'max:100000'], 'notes' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
        ]);
        DB::transaction(function () use ($data, $carePlan, $request) {
            $carePlan->activities()->create($data + ['recorded_by' => $request->user()->id]);
            $updates = ['used_support_minutes' => $carePlan->used_support_minutes + (int) $data['minutes']];
            if ($data['type'] === 'backup') {
                $updates['last_backup_at'] = $data['occurred_at'];
            }
            if ($data['type'] === 'maintenance') {
                $updates['last_maintenance_at'] = $data['occurred_at'];
            }
            $carePlan->update($updates);
        });

        return back()->with('success', 'Care activity logged.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'], 'project_id' => ['nullable', 'exists:projects,id'],
            'type' => ['required', Rule::in(['website_care', 'web_app_maintenance', 'bot_support', 'hosting_monitoring', 'custom_support'])],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'], 'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])],
            'billing_frequency' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'included_support_minutes' => ['required', 'integer', 'min:0'], 'additional_hourly_rate' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'], 'next_billing_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['pending', 'active', 'paused', 'cancelled', 'expired'])],
        ]);
    }

    private function services(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text ?? ''))->map(fn ($line) => trim($line))->filter()->values()->all();
    }
}
