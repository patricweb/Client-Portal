<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\NotificationService;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    private const TYPES = ['proposal', 'scope_of_work', 'contract', 'invoice', 'change_order', 'project_handover', 'care_support_agreement', 'other'];

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
        $source = $data['content'] ?: $template?->content;
        abort_if(blank($source), 422, 'Document content is required.');

        $document = DB::transaction(function () use ($data, $company, $project, $source, $service) {
            $document = Document::create(collect($data)->except('content')->all());
            $document->versions()->create([
                'version' => 1,
                'content' => $service->render($source, $company, $project),
                'snapshot' => $service->snapshot($company, $project),
                'created_by' => request()->user()->id,
            ]);

            return $document;
        });

        return redirect()->route('owner.documents.show', $document)->with('success', 'Document created.');
    }

    public function show(Document $document): View
    {
        return view('owner.documents.show', [
            'document' => $document->load(['company', 'project', 'versions.creator', 'approvals.user', 'attachments']),
            'currentVersion' => $document->currentVersionRecord(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['content' => ['required', 'string'], 'title' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($data, $document, $request) {
            $next = $document->versions()->max('version') + 1;
            $document->versions()->create([
                'version' => $next,
                'content' => $data['content'],
                'snapshot' => $document->currentVersionRecord()?->snapshot,
                'created_by' => $request->user()->id,
            ]);
            $document->update([
                'title' => $data['title'], 'current_version' => $next, 'status' => 'draft',
                'sent_at' => null, 'viewed_at' => null, 'accepted_at' => null, 'signed_at' => null,
            ]);
        });

        return back()->with('success', 'A new document version was created.');
    }

    public function send(Document $document): RedirectResponse
    {
        abort_if($document->status !== 'draft', 422, 'Only a draft can be sent.');
        $status = match ($document->type) {
            'proposal', 'change_order', 'project_handover' => 'awaiting_approval',
            'contract' => 'awaiting_signature',
            default => 'sent',
        };
        $document->currentVersionRecord()?->update(['locked_at' => now()]);
        $document->update(['status' => $status, 'sent_at' => now()]);
        app(NotificationService::class)->send(
            User::where('company_id', $document->company_id)->get(), 'document_sent',
            in_array($status, ['awaiting_approval', 'awaiting_signature']) ? 'action_required' : 'important_update',
            'New document available', $document->title, route('client.documents.show', $document), false
        );

        return back()->with('success', 'Document sent to the client.');
    }

    public function uploadSigned(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->type === 'contract', 422);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:25600']]);
        $file = $request->file('file');
        $path = $file->store("documents/{$document->id}/signed", 'local');
        $document->attachments()->create([
            'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);
        $document->currentVersionRecord()?->update(['locked_at' => now()]);
        $document->update(['status' => 'signed', 'signed_at' => now()]);

        return back()->with('success', 'Signed contract uploaded and locked.');
    }

    public function pdf(Document $document): Response
    {
        $version = $document->currentVersionRecord();
        abort_unless($version, 404);
        $dompdf = new Dompdf;
        $dompdf->loadHtml(view('pdf.document', compact('document', 'version'))->render());
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str($document->title)->slug().'-v'.$version->version.'.pdf"',
        ]);
    }
}
