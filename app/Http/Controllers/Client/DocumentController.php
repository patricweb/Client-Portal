<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\DocumentSignatureService;
use App\Services\DocumentWorkflowService;
use App\Services\NotificationService;
use App\Services\PortalPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('client.documents.index', ['documents' => Document::with('project')
            ->where('company_id', $request->user()->company_id)->where('status', '!=', 'void')
            ->where(fn ($query) => $query->where('status', '!=', 'draft')->orWhereHas('versions', fn ($versions) => $versions->whereNotNull('published_at')))->latest()->get()]);
    }

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);
        if (in_array($document->status, ['sent', 'awaiting_approval', 'awaiting_signature'], true) && ! $document->viewed_at) {
            $document->update(['viewed_at' => now()]);
        }

        return view('client.documents.show', [
            'document' => $document->load(['project', 'approvals.user', 'versions.signedAttachments']),
            'currentVersion' => app(DocumentWorkflowService::class)->version($document, $request->user(), $request->integer('version') ?: null),
        ]);
    }

    public function decide(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,accepted_with_minor_items,changes_requested'],
            'comment' => ['nullable', 'string', 'max:5000', 'required_if:decision,changes_requested'],
            'version' => [$document->pack_template ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($document, $data, $request) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            abort_unless($document->status === 'awaiting_approval', 422);
            abort_if($document->expires_at?->isPast(), 422, 'This offer has expired. Request a revised version.');
            abort_if(isset($data['version']) && (int) $data['version'] !== $document->current_version, 409, 'A different version is now current. Reload before deciding.');
            $version = $document->currentVersionRecord();
            $minorItems = $version->snapshot['minor_items'] ?? null;
            if ($data['decision'] === 'accepted_with_minor_items') {
                abort_unless($document->type === 'delivery_acceptance' && filled($minorItems), 422, 'Minor-item acceptance requires the provider-agreed list and dates.');
            }
            $comment = $data['comment'] ?? null;
            if ($data['decision'] === 'accepted_with_minor_items') {
                $comment = "Agreed minor items:\n".$minorItems."\nClient note: ".$comment;
            }
            $document->approvals()->create([
                'decision' => $data['decision'], 'comment' => $comment,
                'version' => $document->current_version, 'user_id' => $request->user()->id,
                'decided_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]);
            $version->update(['locked_at' => $version->locked_at ?? now(), 'published_at' => $version->published_at ?? now()]);
            if ($data['decision'] !== 'changes_requested') {
                $document->update(['status' => $data['decision'] === 'approved' ? 'accepted' : 'accepted_with_minor_items', 'accepted_at' => now()]);
                $document->lead?->update(['status' => 'accepted']);
            } else {
                $document->update(['status' => 'changes_requested']);
            }
        });
        app(NotificationService::class)->send(
            User::where('role', 'owner')->get(), 'document_decision', 'action_required',
            'Client document decision', "{$document->title}: ".str($data['decision'])->replace('_', ' '), route('owner.documents.show', $document)
        );

        return redirect()->route('client.documents.show', $document)->with('success', 'Your decision was recorded.');
    }

    public function decideStage(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        abort_unless($project->company_id === $request->user()->company_id && $stage->project_id === $project->id, 404);
        abort_unless($stage->requires_approval && $stage->status === 'approval_required', 422);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes_requested'],
            'comment' => ['nullable', 'string', 'max:5000', 'required_if:decision,changes_requested'],
        ]);
        $stage->approvals()->create($data + [
            'version' => 1, 'user_id' => $request->user()->id, 'decided_at' => now(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
        $stage->update(['status' => $data['decision'], 'approved_at' => $data['decision'] === 'approved' ? now() : null]);
        $completed = $project->stages()->whereIn('status', ['approved', 'completed'])->count();
        $project->update(['progress' => (int) round($completed / max(1, $project->stages()->count()) * 100)]);
        app(NotificationService::class)->send(
            User::where('role', 'owner')->get(), 'stage_decision', 'action_required',
            'Project stage decision', "{$project->name} — {$stage->title}: ".str($data['decision'])->replace('_', ' '), route('owner.projects.show', $project)
        );

        return back()->with('success', 'Stage decision recorded.');
    }

    public function pdf(Request $request, Document $document): Response
    {
        $this->authorize('view', $document);
        $version = app(DocumentWorkflowService::class)->version($document, $request->user(), $request->integer('version') ?: null);

        return response(app(PortalPdfService::class)->document($document, $version), 200, ['Content-Type' => 'application/pdf']);
    }

    public function uploadSigned(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);
        app(DocumentSignatureService::class)->upload($request, $document, false);
        app(NotificationService::class)->send(User::where('role', 'owner')->get(), 'signature_received', 'action_required', 'Signed PDF requires review', $document->title, route('owner.documents.show', $document));

        return back()->with('success', 'PDF uploaded for execution review. Uploading alone does not confirm that all required signatures are present.');
    }
}
