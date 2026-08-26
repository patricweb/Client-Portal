<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;

class PortalPdfService
{
    public function render(string $view, array $data): string
    {
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => public_path()]);
        $pdf->loadHtml(view($view, $data)->render());
        $pdf->setPaper('letter');
        $pdf->render();
        $pdf->getCanvas()->page_text(480, 765, 'Page {PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.4, 0.45, 0.5]);

        return $pdf->output();
    }

    public function document(Document $document, DocumentVersion $version, bool $freeze = false): string
    {
        if ($version->pdf_path) {
            return $this->archived($version->pdf_path, $version->pdf_sha256);
        }
        if (! $version->published_at && $version->version === $document->current_version && ! in_array($document->status, ['draft', 'void'])) {
            $version->update(['published_at' => $document->sent_at ?? $version->created_at]);
        }
        $bytes = $this->render('pdf.document', compact('document', 'version'));
        if ($freeze || $version->published_at) {
            $hash = hash('sha256', $bytes);
            $path = "documents/{$document->id}/versions/{$version->version}/{$hash}.pdf";
            abort_unless(Storage::disk('local')->put($path, $bytes), 500, 'Could not archive document PDF.');
            $version->update(['pdf_path' => $path, 'pdf_sha256' => $hash]);
        }

        return $bytes;
    }

    public function invoice(Invoice $invoice, bool $freeze = false): string
    {
        if ($invoice->pdf_path) {
            return $this->archived($invoice->pdf_path, $invoice->pdf_sha256);
        }
        $bytes = $this->render('pdf.invoice', compact('invoice'));
        if ($freeze) {
            $hash = hash('sha256', $bytes);
            $path = "invoices/{$invoice->id}/{$hash}.pdf";
            abort_unless(Storage::disk('local')->put($path, $bytes), 500, 'Could not archive invoice PDF.');
            $invoice->update(['pdf_path' => $path, 'pdf_sha256' => $hash]);
        }

        return $bytes;
    }

    private function archived(string $path, ?string $hash): string
    {
        abort_unless(Storage::disk('local')->exists($path), 409, 'The archived PDF is missing. Restore it from backup; it will not be silently regenerated.');
        $bytes = Storage::disk('local')->get($path);
        abort_unless($hash && hash_equals($hash, hash('sha256', $bytes)), 409, 'Archived PDF integrity check failed.');

        return $bytes;
    }
}
