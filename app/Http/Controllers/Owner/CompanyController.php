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
        $data = $this->validated($request);
        $temporaryPassword = Str::password(16);

        $company = DB::transaction(function () use ($data, $temporaryPassword) {
            $company = Company::create(collect($data)->except(['contact_name', 'contact_email', 'contact_phone', 'create_access'])->all());
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
                'email' => $data['contact_email'], 'phone' => $data['contact_phone'] ?? null, 'is_primary' => true,
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

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'billing_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'website' => ['nullable', 'url'],
            'billing_address' => ['nullable', 'string'], 'timezone' => ['required', 'timezone'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'MDL'])], 'internal_notes' => ['nullable', 'string'],
            'contact_name' => ['required', 'string', 'max:255'], 'contact_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_phone' => ['nullable', 'string', 'max:50'], 'create_access' => ['nullable', 'boolean'],
        ]);
    }
}
