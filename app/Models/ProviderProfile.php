<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function current(): self
    {
        return static::findOrFail(1);
    }

    public function missing(bool $bank = false, bool $business = false): array
    {
        $required = ['legal_name', 'address', 'country', 'email', 'details_confirmed'];
        if ($business) {
            $required = array_merge($required, ['business_status', 'registration_id']);
        }
        if ($bank) {
            $required = array_merge($required, ['bank_name', 'beneficiary', 'iban', 'swift', 'currency', 'bank_confirmed']);
        }

        return array_values(array_filter($required, fn ($key) => blank($this->details[$key] ?? null)));
    }

    public function paymentInstructions(): string
    {
        $labels = ['beneficiary' => 'Beneficiary', 'bank_name' => 'Bank', 'iban' => 'Account / IBAN', 'swift' => 'SWIFT / BIC', 'bank_address' => 'Bank address', 'correspondent' => 'Correspondent instructions', 'currency' => 'Account currency', 'fee_rule' => 'Bank fees'];

        return collect($labels)->filter(fn ($label, $key) => filled($this->details[$key] ?? null))
            ->map(fn ($label, $key) => $label.': '.$this->details[$key])->implode("\n");
    }
}
