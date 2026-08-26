<x-layouts.app title="Provider settings - Ikira Portal">
    <div class="mb-7"><p class="text-sm font-medium text-indigo-600">Settings</p><h1 class="mt-1 text-3xl font-semibold">Legal provider & payment details</h1><p class="mt-2 max-w-3xl text-slate-500">Ikira is the portal brand. Contracts and invoices use the legal person below. Changes apply to new snapshots only.</p></div>
    <form method="POST" action="{{ route('owner.settings.provider.update') }}" class="max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6">@csrf @method('PUT')
        <div class="grid gap-5 md:grid-cols-2">
            @foreach($fields as $key => $label)
                <label class="block text-sm font-medium">{{ $label }}
                    <textarea name="{{ $key }}" rows="{{ in_array($key, ['address','correspondent','fee_rule','tax_note']) ? 3 : 1 }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">{{ old($key, $profile->details[$key] ?? '') }}</textarea>
                </label>
            @endforeach
            <label class="text-sm font-medium">Bank account currency<select name="currency" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5">@foreach(['USD','EUR','MDL'] as $currency)<option @selected(old('currency', $profile->details['currency'] ?? 'USD') === $currency)>{{ $currency }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">Default invoice payment period (calendar days)<input type="number" name="payment_due_days" min="1" max="90" required value="{{ old('payment_due_days', $profile->details['payment_due_days'] ?? 7) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label>
        </div>
        <div class="space-y-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
            <p>Do not enter passwords, card security codes or identity-document scans. Confirm the appropriate signing, business and tax arrangements independently; these checkboxes are your review record, not legal certification.</p>
            <label class="flex gap-3"><input type="checkbox" name="details_confirmed" value="1" @checked(old('details_confirmed', $profile->details['details_confirmed'] ?? false))><span>I have checked the legal identity and the arrangements required to use these documents.</span></label>
            <label class="flex gap-3"><input type="checkbox" name="bank_confirmed" value="1" @checked(old('bank_confirmed', $profile->details['bank_confirmed'] ?? false))><span>The bank has confirmed these receiving instructions and their suitability for these payments.</span></label>
        </div>
        <button class="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-medium text-white">Save provider profile</button>
    </form>
</x-layouts.app>
