<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

class InvoicePaymentDescriptionTest extends TestCase
{
    public function test_it_generates_a_description_with_an_agreement_number(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'INV-2026-00001',
            'snapshot' => ['agreement_number' => 'AGR-2026-00001'],
        ]);

        $this->assertSame(
            'Payment for software development services under Invoice INV-2026-00001 and Agreement AGR-2026-00001.',
            $invoice->paymentDescription(),
        );
    }

    public function test_it_generates_a_description_without_an_agreement_number(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'INV-2026-00002',
            'snapshot' => [],
        ]);

        $this->assertSame(
            'Payment for software development services under Invoice INV-2026-00002.',
            $invoice->paymentDescription(),
        );
    }
}
