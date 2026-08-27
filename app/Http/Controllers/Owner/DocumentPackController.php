<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProviderProfile;
use App\Services\DocumentPackService;
use App\Services\DocumentService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentPackController extends Controller
{
    public function create(Request $request, DocumentPackService $pack)
    {
        return $this->form($request, $pack);
    }

    public function edit(Request $request, Document $document, DocumentPackService $pack)
    {
        abort_unless($document->pack_template, 404);

        return $this->form($request, $pack, $document);
    }

    private function form(Request $request, DocumentPackService $pack, ?Document $document = null)
    {
        $snapshot = $document?->currentVersionRecord()?->snapshot ?? [];
        $key = $request->input('template', $document?->pack_template ?? 'project_confirmation');
        $definition = $pack->definition($key);
        if ($document && isset($snapshot['source_hash'])) {
            abort_unless(hash_equals($snapshot['source_hash'], hash('sha256', $pack->source($key))), 409, 'The template source changed. Create a fresh document and review its fields; old field positions will not be reused.');
        }
        $company = Company::find($request->input('company_id', $document?->company_id));
        $project = Project::find($request->input('project_id', $document?->project_id));
        abort_if($project && (! $company || $project->company_id !== $company->id), 422, 'Select a project belonging to this client.');
        $parent = Document::find($request->input('parent_document_id', $document?->parent_document_id));
        if ($parent) {
            $this->validateParent($parent, $company, $project, $definition);
        }
        $commercial = $snapshot['commercial'] ?? ['price' => $project?->price, 'target_date' => $project?->target_completion_date?->format('Y-m-d')];
        $commercial['document_number'] = $document?->document_number ?? 'Assigned when the draft is created';
        $saved = session()->getOldInput('fields', $snapshot['fields'] ?? []);
        $prepared = $company ? $pack->prepare($key, $company, $project, $parent, $commercial, $saved) : null;

        return view('owner.document-pack.form', compact('key', 'definition', 'company', 'project', 'parent', 'commercial', 'prepared', 'document') + [
            'templates' => DocumentPackService::TEMPLATES,
            'companies' => Company::orderBy('name')->get(),
            'projects' => $company ? $company->projects()->orderBy('name')->get() : collect(),
            'parents' => $company && $definition['parent'] ? Document::where('company_id', $company->id)->where('type', $definition['parent'])
                ->whereIn('status', $definition['parent_statuses'])->where('project_id', $project?->id)->get() : collect(),
            'profile' => ProviderProfile::current(),
        ]);
    }

    public function store(Request $request, DocumentPackService $pack, DocumentService $service)
    {
        return $this->save($request, $pack, $service);
    }

    public function update(Request $request, Document $document, DocumentPackService $pack, DocumentService $service)
    {
        abort_unless($document->pack_template, 404);

        return $this->save($request, $pack, $service, $document);
    }

    private function save(Request $request, DocumentPackService $pack, DocumentService $service, ?Document $document = null)
    {
        $data = $request->validate([
            'template' => ['required', Rule::in(array_keys(DocumentPackService::TEMPLATES))],
            'company_id' => ['required', 'exists:companies,id'], 'project_id' => ['nullable', 'exists:projects,id'],
            'parent_document_id' => ['nullable', 'exists:documents,id'], 'title' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'required_if:template,project_confirmation,change_confirmation', 'numeric', 'min:0', 'max:999999999'], 'target_date' => ['nullable', 'date'],
            'fields' => ['nullable', 'array', 'max:300'], 'fields.*' => ['nullable', 'string', 'max:8000'],
            'base_version' => ['nullable', 'integer', 'min:1'], 'source_hash' => ['required', 'string', 'size:64'],
            'minor_items' => ['nullable', 'string', 'max:5000'],
        ]);
        $definition = $pack->definition($data['template']);
        abort_unless(hash_equals(hash('sha256', $pack->source($data['template'])), $data['source_hash']), 409, 'Template changed. Reload the form before saving.');
        $company = Company::findOrFail($data['company_id']);
        $project = isset($data['project_id']) ? Project::findOrFail($data['project_id']) : null;
        abort_if($project && $project->company_id !== $company->id, 422, 'Project does not belong to this client.');
        abort_if($project && $project->currency !== 'USD', 422, 'This document pack uses USD. Use a reviewed custom template for another currency.');
        abort_unless($project, 422, 'Select a project for this confirmation.');
        $parent = isset($data['parent_document_id']) ? Document::findOrFail($data['parent_document_id']) : null;
        if ($parent) {
            $this->validateParent($parent, $company, $project, $definition);
        }
        if ($document) {
            abort_unless($document->company_id === $company->id && $document->project_id === $project?->id && $document->pack_template === $data['template'], 422, 'A revision must keep its client, project and document type.');
        }
        $document = DB::transaction(function () use ($document, $data, $definition, $company, $project, $parent, $pack, $service, $request) {
            if ($document) {
                $document = Document::lockForUpdate()->findOrFail($document->id);
                abort_unless((int) ($data['base_version'] ?? 0) === $document->current_version, 409, 'A newer version exists. Reload before editing.');
                $next = $document->current_version + 1;
            } else {
                $document = Document::create(['company_id' => $company->id, 'project_id' => $project?->id, 'type' => $definition['type'], 'title' => $data['title'], 'pack_template' => $data['template']]);
                $document->update(['document_number' => $definition['prefix'].'-'.now()->year.'-'.str_pad((string) $document->id, 5, '0', STR_PAD_LEFT)]);
                $next = 1;
            }
            $commercial = ['price' => $data['price'] ?? null, 'currency' => 'USD', 'target_date' => $data['target_date'] ?? null, 'document_number' => $document->document_number];
            if ($data['template'] === 'change_confirmation' && $parent) {
                $commercial['previous_total'] = app(InvoiceService::class)->agreementTotal($parent);
            }
            $prepared = $pack->prepare($data['template'], $company, $project, $parent, $commercial, $data['fields'] ?? []);
            foreach ($prepared['fields'] as $id => $field) {
                if (! $field['automatic'] && (mb_strlen($field['value']) > $field['max_length'] || ($field['table_cell'] && substr_count($field['value'], "\n") > 14))) {
                    throw ValidationException::withMessages(['fields.'.$id => 'Keep table entries within 1,000 characters and 15 lines. Reference a separate specification for longer details.']);
                }
            }
            $snapshot = $service->snapshot($company, $project) + [
                'title' => $data['title'], 'type' => $definition['type'], 'document_number' => $document->document_number,
                'pack_template' => $data['template'], 'source_hash' => $prepared['source_hash'],
                'commercial' => $commercial, 'fields' => collect($prepared['fields'])->reject(fn ($field) => $field['automatic'])->map(fn ($field) => $field['value'])->all(),
                'missing_fields' => $prepared['missing'], 'parent_id' => $parent?->id, 'parent_version' => $parent?->current_version,
                'minor_items' => $data['template'] === 'delivery_confirmation' ? ($data['minor_items'] ?? null) : null,
            ];
            if (filled($snapshot['minor_items'])) {
                $prepared['html'] .= '<h2>Provider minor-item commitments for portal acceptance</h2><p>'.nl2br(e($snapshot['minor_items'])).'</p><p>This list is offered by the provider for this version. It becomes the agreed minor-item list only when the client explicitly selects acceptance with these minor items in the portal.</p>';
            }
            $document->versions()->create(['version' => $next, 'content' => $prepared['html'], 'snapshot' => $snapshot, 'created_by' => $request->user()->id]);
            $document->update(['title' => $data['title'], 'parent_document_id' => $parent?->id, 'current_version' => $next, 'status' => 'draft', 'sent_at' => null, 'viewed_at' => null, 'accepted_at' => null, 'signed_at' => null]);

            return $document;
        });

        return redirect()->route('owner.documents.show', $document)->with('success', 'Draft saved. Review the preview and remaining fields before sending.');
    }

    private function validateParent(Document $parent, ?Company $company, ?Project $project, array $definition): void
    {
        abort_unless($company && $parent->company_id === $company->id && $parent->type === $definition['parent'], 422, 'The related confirmation must match this client and document type.');
        abort_unless($parent->project_id === $project?->id && in_array($parent->status, $definition['parent_statuses'], true), 422, 'Select the accepted Project Confirmation for this project.');
    }
}
