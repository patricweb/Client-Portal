<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
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
        $project = $attachment->attachable instanceof Project
            ? $attachment->attachable
            : ($attachment->attachable?->project ?? null);
        abort_unless($project, 404);
        $this->authorizeProject($request, $project);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless($request->user()->isOwner() || $project->company_id === $request->user()->company_id, 404);
    }
}
