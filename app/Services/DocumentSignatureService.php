<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentSignatureService
{
    public function upload(Request $request, Document $document, bool $confirm): void
    {
        $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:25600'],
            'execution_confirmed' => $confirm ? ['required', 'accepted'] : ['nullable'],
        ]);
        DB::transaction(function () use ($request, $document, $confirm) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            abort_unless($document->requiresSignature() && in_array($document->status, ['awaiting_signature', 'signature_received']), 422, 'This document is not awaiting signatures.');
            abort_unless($document->current_version === $request->integer('version'), 409, 'The document version changed. Reload before uploading.');
            $version = $document->currentVersionRecord();
            abort_unless($version?->published_at, 422, 'Send this version before recording signatures.');
            $file = $request->file('file');
            $path = $file->store("documents/{$document->id}/versions/{$version->version}/signed", 'local');
            $document->attachments()->create([
                'document_version_id' => $version->id, 'uploaded_by' => $request->user()->id,
                'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
            ]);
            if ($confirm) {
                $version->update(['signed_at' => now(), 'locked_at' => $version->locked_at ?? now()]);
            }
            $document->update(['status' => $confirm ? 'signed' : 'signature_received', 'signed_at' => $confirm ? now() : null]);
            app(ActivityLogger::class)->log($confirm ? 'document.execution_confirmed' : 'document.signature_received',
                $confirm ? 'Staff confirmed the required execution and consents.' : 'Client uploaded a PDF for execution review.',
                $document, 'public', ['version' => $version->version, 'sha256' => hash_file('sha256', $file->getRealPath())], $document->company_id, $document->project_id);
        });
    }

    public function confirm(Request $request, Document $document): void
    {
        $data = $request->validate(['version' => ['required', 'integer'], 'attachment_id' => ['required', 'integer'], 'execution_confirmed' => ['required', 'accepted']]);
        DB::transaction(function () use ($document, $data) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            abort_unless($document->requiresSignature() && $document->status === 'signature_received', 422);
            abort_unless($document->current_version === (int) $data['version'], 409);
            $version = $document->currentVersionRecord();
            $attachment = $document->attachments()->where('document_version_id', $version->id)->findOrFail($data['attachment_id']);
            $version->update(['signed_at' => now()]);
            $document->update(['status' => 'signed', 'signed_at' => now()]);
            app(ActivityLogger::class)->log('document.execution_confirmed', 'Staff confirmed the required execution and consents.', $document, 'public', ['version' => $version->version, 'attachment_id' => $attachment->id], $document->company_id, $document->project_id);
        });
    }
}
