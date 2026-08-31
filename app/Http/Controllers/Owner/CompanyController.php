<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('owner.companies.index', ['companies' => Company::withCount('projects')->latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('owner.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $temporaryPassword = Str::password(16);

        $company = DB::transaction(function () use ($data, $temporaryPassword) {
            $company = Company::create(collect($data)->except(['contact_name', 'contact_email', 'contact_phone', 'contact_job_title', 'create_access'])->all());
            $user = null;

            if ($data['create_access'] ?? false) {
                $user = User::create([
                    'company_id' => $company->id, 'name' => $data['contact_name'], 'email' => $data['contact_email'],
                    'password' => $temporaryPassword, 'role' => UserRole::Client, 'status' => AccountStatus::Invited,
                    'must_change_password' => true,
                ]);
            }

            CompanyContact::create([
                'company_id' => $company->id, 'user_id' => $user?->id, 'name' => $data['contact_name'],
                'email' => $data['contact_email'], 'phone' => $data['contact_phone'] ?? null,
                'job_title' => $data['contact_job_title'], 'is_primary' => true,
            ]);

            return $company;
        });

        $message = 'Client company created.';
        if ($data['create_access'] ?? false) {
            session()->flash('temporary_credentials', ['email' => $data['contact_email'], 'password' => $temporaryPassword]);
        }

        return redirect()->route('owner.companies.show', $company)->with('success', $message);
    }

    public function show(Company $company): View
    {
        return view('owner.companies.show', ['company' => $company->load(['contacts.user', 'projects.stages'])]);
    }

    public function edit(Company $company): View
    {
        return view('owner.companies.edit', ['company' => $company->load('contacts.user'), 'contact' => $company->contacts()->where('is_primary', true)->firstOrFail()]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $contact = $company->contacts()->where('is_primary', true)->firstOrFail();
        $data = $this->validated($request, false, $contact);

        DB::transaction(function () use ($company, $contact, $data) {
            $company->update(collect($data)->except(['contact_name', 'contact_email', 'contact_phone', 'contact_job_title', 'create_access'])->all());
            $contact->update([
                'name' => $data['contact_name'],
                'email' => $data['contact_email'],
                'phone' => $data['contact_phone'] ?? null,
                'job_title' => $data['contact_job_title'],
            ]);
            $contact->user?->update(['name' => $data['contact_name'], 'email' => $data['contact_email']]);
        });

        return redirect()->route('owner.companies.show', $company)->with('success', 'Client legal and contact details updated. Existing document snapshots are unchanged.');
    }

    private function validated(Request $request, bool $creating, ?CompanyContact $contact = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'billing_name' => ['required', 'string', 'max:255'],
            'jurisdiction' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'website' => ['nullable', 'url'],
            'billing_address' => ['required', 'string', 'max:2000'], 'timezone' => ['required', 'timezone'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])], 'internal_notes' => ['nullable', 'string'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($contact?->user_id)],
            'contact_job_title' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'create_access' => [$creating ? 'nullable' : 'exclude', 'boolean'],
        ]);
    }
}
