@if(!$invoice->snapshot)
@include('pdf.invoice-legacy')
@else
<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:44px 44px 54px}body{font-family:DejaVu Sans,sans-serif;color:#000;font-size:10px;line-height:1.5}header{border-bottom:2px solid #000;padding-bottom:14px}h1{font-size:22px;margin:6px 0}h2{font-size:12px;color:#000;margin:16px 0 5px;border-bottom:1px solid #000;padding-bottom:2px}p{margin:5px 0}.muted{color:#000;font-size:9px}.draft{color:#000;font-weight:bold;border:1px solid #000;display:inline-block;padding:2px 5px}table{border-collapse:collapse;width:100%;margin-top:16px}th{background:#fff;color:#000;border-bottom:2px solid #000}th,td{padding:8px;border-bottom:1px solid #000;text-align:left;vertical-align:top}tr{page-break-inside:avoid}.right{text-align:right}.totals{width:55%;margin-left:auto}.totals td{padding:5px}.grand{font-weight:bold;font-size:13px;border-top:2px solid #000}.instructions{white-space:pre-wrap;overflow-wrap:break-word}.footer{position:fixed;bottom:-22px;font-size:8px;color:#000}
</style></head><body>
@php($provider = $invoice->snapshot['provider'] ?? [])
@php($buyer = $invoice->snapshot['company'] ?? [])
<header><div>{{ $provider['legal_name'] ?? '' }}</div><h1>{{ $invoice->kind === 'advance' ? 'ADVANCE INVOICE' : ($invoice->kind === 'final' ? 'FINAL MILESTONE INVOICE' : 'INVOICE') }}</h1><div>{{ $invoice->invoice_number }} | {{ $invoice->currency }}</div>@if($invoice->status === 'draft')<p class="draft">DRAFT - not yet issued</p>@endif</header>
<h2>Provider / bill to</h2><p><strong>{{ $provider['legal_name'] ?? '' }}</strong><br>{{ $provider['address'] ?? '' }}<br>{{ $provider['country'] ?? '' }} | {{ $provider['email'] ?? '' }}</p>@if($provider['registration_id'] ?? null)<p>Registration / tax ID: {{ $provider['registration_id'] }}</p>@endif
<p><strong>Client: {{ ($buyer['billing_name'] ?? null) ?: ($buyer['name'] ?? '') }}</strong>@if(filled($buyer['billing_address'] ?? null))<br>{{ $buyer['billing_address'] }}@endif @if(filled($buyer['email'] ?? null))<br>{{ $buyer['email'] }}@endif</p>
<p>Issued: {{ $invoice->issue_date->format('F j, Y') }} | Due: {{ $invoice->due_date->format('F j, Y') }}</p>
@php($agreementNumber = $invoice->snapshot['agreement_number'] ?? $invoice->snapshot['sow_number'] ?? null)
@php($agreementVersion = $invoice->snapshot['agreement_version'] ?? $invoice->snapshot['sow_version'] ?? null)
@php($deliveryNumber = $invoice->snapshot['delivery_number'] ?? $invoice->snapshot['acceptance_number'] ?? null)
@php($deliveryVersion = $invoice->snapshot['delivery_version'] ?? $invoice->snapshot['acceptance_version'] ?? null)
@if($agreementNumber)<p>Project confirmation: {{ $agreementNumber }} / v{{ $agreementVersion }} @if($deliveryNumber) | Delivery confirmation: {{ $deliveryNumber }} / v{{ $deliveryVersion }} @endif</p>@endif
<table><thead><tr><th>Description</th><th>Qty</th><th class="right">Amount ({{ $invoice->currency }})</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr><td>{{ $item->description }}</td><td>{{ $item->quantity }}</td><td class="right">{{ number_format($item->total,2) }}</td></tr>@endforeach</tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="right">{{ number_format($invoice->subtotal,2) }}</td></tr><tr><td>Discount</td><td class="right">-{{ number_format($invoice->discount,2) }}</td></tr><tr><td>Tax</td><td class="right">{{ number_format($invoice->tax_amount,2) }}</td></tr><tr class="grand"><td>Total this invoice</td><td class="right">{{ $invoice->currency }} {{ number_format($invoice->total,2) }}</td></tr></table>
<p class="muted">Tax treatment: {{ $invoice->tax_description ?: 'Not confirmed - draft only' }}</p>
@if($invoice->kind !== 'standard')<p>This invoice bills only the stated milestone. Earlier invoices remain separate, even if unpaid; this is not a second charge for the advance.</p>@endif
<h2>Payment instructions</h2><p class="instructions">{{ $invoice->payment_instructions }}</p><p><strong>Payment description / reference:</strong><br>{{ $invoice->paymentDescription() }}</p>
@if($invoice->public_notes)<h2>Notes</h2><p class="instructions">{{ $invoice->public_notes }}</p>@endif
<p class="muted">Payment request, not a receipt or delivery confirmation. Confirm changed bank instructions through a previously trusted channel. Current payment status is available in the portal; this issued PDF is not rewritten when payments arrive.</p>
<div class="footer">{{ $provider['legal_name'] ?? '' }} | {{ $invoice->invoice_number }} | Issued invoice record</div>
</body></html>
@endif
