<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ProviderProfileController extends Controller
{
    public const FIELDS = [
        'legal_name' => 'Legal name / contracting person', 'brand_name' => 'Display brand (not the contracting party)',
        'address' => 'Legal / correspondence address', 'country' => 'Country', 'email' => 'Business contact email',
        'registration_id' => 'Registration / tax identifier, if applicable', 'tax_note' => 'Verified invoice tax treatment',
        'beneficiary' => 'Bank-confirmed beneficiary name', 'bank_name' => 'Bank legal name',
        'iban' => 'Account / IBAN', 'swift' => 'SWIFT / BIC', 'bank_address' => 'Bank address, if required',
        'correspondent' => 'Correspondent instructions, if required', 'fee_rule' => 'Agreed default bank-fee allocation',
    ];

    public function edit()
    {
        return view('owner.settings.provider', ['profile' => ProviderProfile::current(), 'fields' => self::FIELDS]);
    }

    public function update(Request $request)
    {
        $rules = collect(self::FIELDS)->mapWithKeys(fn ($label, $key) => [$key => ['nullable', 'string', 'max:2000']])->all();
        $rules['legal_name'] = ['required', 'string', 'max:255'];
        $rules['email'] = ['nullable', 'email', 'max:255'];
        $data = $request->validate($rules + [
            'currency' => ['required', 'in:USD,EUR,MDL'], 'payment_due_days' => ['required', 'integer', 'min:1', 'max:90'],
            'details_confirmed' => ['nullable', 'accepted'], 'bank_confirmed' => ['nullable', 'accepted'],
        ]);
        $data['details_confirmed'] = $request->boolean('details_confirmed');
        $data['bank_confirmed'] = $request->boolean('bank_confirmed');
        $profile = ProviderProfile::current();
        $profile->update(['details' => $data]);
        app(ActivityLogger::class)->log('provider.updated', 'Provider profile updated. Existing snapshots are unchanged.', $profile, 'internal');

        return back()->with('success', 'Provider profile saved. Existing document and invoice snapshots were not changed.');
    }
}
