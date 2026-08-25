<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Document;
use App\Models\Project;
use App\Models\RequestMessage;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $request->validate(['file' => ['required', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,zip,txt']]);
        $file = $request->file('file');
        $path = $file->store("projects/{$project->id}", 'local');

        $project->attachments()->create([
            'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);

        return back()->with('success', 'File uploaded.');
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $document = $attachment->attachable instanceof Document ? $attachment->attachable : null;
        if ($document) {
            abort_unless($request->user()->hasPermission('manage_documents') || $document->company_id === $request->user()->company_id, 404);

            return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
        }

        $supportRequest = $attachment->attachable instanceof SupportRequest
            ? $attachment->attachable
            : ($attachment->attachable instanceof RequestMessage ? $attachment->attachable->request : null);
        if ($supportRequest) {
            abort_unless($request->user()->hasPermission('manage_requests') || $supportRequest->company_id === $request->user()->company_id, 404);
            if ($attachment->attachable instanceof RequestMessage && $attachment->attachable->is_internal && ! $request->user()->hasPermission('manage_requests')) {
                abort(404);
            }

            return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
        }

        $project = $attachment->attachable instanceof Project
            ? $attachment->attachable
            : ($attachment->attachable?->project ?? null);
        abort_unless($project, 404);
        $this->authorizeProject($request, $project);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(($request->user()->hasPermission('manage_projects') && $request->user()->canAccessProject($project)) || $project->company_id === $request->user()->company_id, 404);
    }
}
