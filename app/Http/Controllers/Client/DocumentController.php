<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\NotificationService;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('client.documents.index', ['documents' => Document::with('project')
            ->where('company_id', $request->user()->company_id)->whereNotIn('status', ['draft', 'void'])->latest()->get()]);
    }

    public function show(Request $request, Document $document): View
    {
        $this->authorize('view', $document);
        if (in_array($document->status, ['sent', 'awaiting_approval', 'awaiting_signature'], true) && ! $document->viewed_at) {
            $document->update(['viewed_at' => now()]);
        }

        return view('client.documents.show', [
            'document' => $document->load(['project', 'approvals.user', 'attachments']),
            'currentVersion' => $document->currentVersionRecord(),
        ]);
    }

    public function decide(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);
        abort_unless($document->status === 'awaiting_approval', 422);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes_requested'],
            'comment' => ['nullable', 'string', 'max:5000', 'required_if:decision,changes_requested'],
        ]);
        $document->approvals()->create($data + [
            'version' => $document->current_version, 'user_id' => $request->user()->id,
            'decided_at' => now(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
        if ($data['decision'] === 'approved') {
            $document->currentVersionRecord()?->update(['locked_at' => now()]);
            $document->update(['status' => 'accepted', 'accepted_at' => now()]);
            $document->lead?->update(['status' => 'accepted']);
        } else {
            $document->update(['status' => 'draft']);
        }
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
        $version = $document->currentVersionRecord();
        abort_unless($version, 404);
        $dompdf = new Dompdf;
        $dompdf->loadHtml(view('pdf.document', compact('document', 'version'))->render());
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf']);
    }
}
