<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentHtmlService;
use App\Services\DocumentService;
use App\Services\DocumentSignatureService;
use App\Services\DocumentWorkflowService;
use App\Services\NotificationService;
use App\Services\PortalPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    private const TYPES = ['project_confirmation', 'change_confirmation', 'delivery_confirmation', 'proposal', 'scope_of_work', 'contract', 'invoice', 'change_order', 'delivery_acceptance', 'project_handover', 'care_support_agreement', 'other'];

    public function index(): View
    {
        return view('owner.documents.index', ['documents' => Document::with(['company', 'project'])->latest()->paginate(25)]);
    }

    public function create(): View
    {
        return view('owner.documents.create', [
            'companies' => Company::orderBy('name')->get(),
            'projects' => Project::with('company')->orderBy('name')->get(),
            'templates' => DocumentTemplate::where('is_active', true)->orderBy('name')->get(),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request, DocumentService $service): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'document_template_id' => ['nullable', 'exists:document_templates,id'],
            'type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        $company = Company::with('contacts')->findOrFail($data['company_id']);
        $project = isset($data['project_id']) ? Project::findOrFail($data['project_id']) : null;
        abort_if($project && $project->company_id !== $company->id, 422, 'Project does not belong to the selected company.');
        $template = isset($data['document_template_id']) ? DocumentTemplate::find($data['document_template_id']) : null;
        abort_if($template && (! $template->is_active || $template->type !== $data['type']), 422, 'Select an active template of the same document type.');
        $source = ($data['content'] ?? null) ?: $template?->content;
        abort_if(blank($source), 422, 'Document content is required.');

        $document = DB::transaction(function () use ($data, $company, $project, $source, $service) {
            $document = Document::create(collect($data)->except('content')->all());
            $document->versions()->create([
                'version' => 1,
                'content' => app(DocumentHtmlService::class)->clean($service->render($source, $company, $project)),
                'snapshot' => $service->snapshot($company, $project) + ['title' => $document->title, 'type' => $document->type],
                'created_by' => request()->user()->id,
            ]);

            return $document;
        });

        return redirect()->route('owner.documents.show', $document)->with('success', 'Document created.');
    }

    public function show(Request $request, Document $document): View
    {
        return view('owner.documents.show', [
            'document' => $document->load(['company', 'project', 'versions.creator', 'versions.signedAttachments', 'approvals.user', 'attachments']),
            'currentVersion' => app(DocumentWorkflowService::class)->version($document, $request->user(), $request->integer('version') ?: null),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        abort_if($document->pack_template, 422, 'Use Edit details to revise a v2 document.');
        $data = $request->validate(['content' => ['required', 'string'], 'title' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($data, $document, $request) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            abort_if($request->filled('base_version') && $request->integer('base_version') !== $document->current_version, 409, 'A newer version exists.');
            $old = $document->currentVersionRecord();
            if ($document->status !== 'draft' && $old && ! $old->published_at) {
                $old->update(['published_at' => $document->sent_at ?? $old->locked_at ?? now()]);
            }
            if ($old?->published_at) {
                app(PortalPdfService::class)->document($document, $old, true);
            }
            $next = $document->versions()->max('version') + 1;
            $document->versions()->create([
                'version' => $next,
                'content' => app(DocumentHtmlService::class)->clean($data['content']),
                'snapshot' => array_replace($old?->snapshot ?? [], ['title' => $data['title']]),
                'created_by' => $request->user()->id,
            ]);
            $document->update([
                'title' => $data['title'], 'current_version' => $next, 'status' => 'draft',
                'sent_at' => null, 'viewed_at' => null, 'accepted_at' => null, 'signed_at' => null,
            ]);
        });

        return back()->with('success', 'A new document version was created.');
    }

    public function send(Request $request, Document $document): RedirectResponse
    {
        $request->validate(['version' => [$document->pack_template ? 'required' : 'nullable', 'integer']]);
        $status = DB::transaction(function () use ($document, $request) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            abort_if($request->filled('version') && $request->integer('version') !== $document->current_version, 409, 'The draft changed. Review the current version before sending.');
            abort_if($document->status !== 'draft', 422, 'Only a draft can be sent.');
            abort_if($document->expires_at?->isPast(), 422, 'The document has expired. Create a revised version.');
            app(DocumentWorkflowService::class)->assertReady($document);
            $status = $document->requiresSignature() ? 'awaiting_signature' : (in_array($document->type, ['project_confirmation', 'change_confirmation', 'delivery_confirmation', 'proposal', 'delivery_acceptance', 'project_handover']) ? 'awaiting_approval' : 'sent');
            $version = $document->currentVersionRecord();
            $version->update(['locked_at' => now(), 'published_at' => now()]);
            app(PortalPdfService::class)->document($document, $version, true);
            $document->update(['status' => $status, 'sent_at' => now()]);

            return $status;
        });
        app(NotificationService::class)->send(
            User::where('company_id', $document->company_id)->get(), 'document_sent',
            in_array($status, ['awaiting_approval', 'awaiting_signature']) ? 'action_required' : 'important_update',
            'New document available', $document->title, route('client.documents.show', ['document' => $document, 'version' => $document->current_version]), false
        );

        return back()->with('success', 'Document sent to the client.');
    }

    public function uploadSigned(Request $request, Document $document): RedirectResponse
    {
        app(DocumentSignatureService::class)->upload($request, $document, true);

        return back()->with('success', 'Executed PDF saved against this exact version.');
    }

    public function confirmSigned(Request $request, Document $document): RedirectResponse
    {
        app(DocumentSignatureService::class)->confirm($request, $document);

        return back()->with('success', 'Execution review recorded for this version.');
    }

    public function pdf(Request $request, Document $document): Response
    {
        $version = app(DocumentWorkflowService::class)->version($document, $request->user(), $request->integer('version') ?: null);

        return response(app(PortalPdfService::class)->document($document, $version), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str($document->title)->slug().'-v'.$version->version.'.pdf"',
        ]);
    }
}
