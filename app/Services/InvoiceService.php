<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function nextNumber(): string
    {
        $year = now()->format('Y');
        $latest = Invoice::where('invoice_number', 'like', "INV-{$year}-%")
            ->lockForUpdate()->orderByDesc('invoice_number')->value('invoice_number');
        $sequence = $latest ? ((int) substr($latest, -5)) + 1 : 1;

        return sprintf('INV-%s-%05d', $year, $sequence);
    }

    public function create(array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($data, $items) {
            $invoice = Invoice::create($data + ['invoice_number' => $this->nextNumber(), 'subtotal' => 0, 'total' => 0]);
            $subtotal = 0;
            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $total = round($quantity * (float) $item['unit_price'], 2);
                $subtotal += $total;
                $invoice->items()->create($item + ['quantity' => $quantity, 'total' => $total]);
            }
            $invoice->update(['subtotal' => $subtotal, 'total' => max(0, $subtotal - (float) ($data['discount'] ?? 0))]);

            return $invoice;
        });
    }
}
