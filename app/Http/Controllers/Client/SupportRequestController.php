<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('client.requests.index', ['requests' => SupportRequest::with('project')->where('company_id', $request->user()->company_id)->latest()->get()]);
    }

    public function create(Request $request): View
    {
        return view('client.requests.create', ['projects' => Project::where('company_id', $request->user()->company_id)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
            'category' => ['required', Rule::in(['bug', 'content_update', 'technical_issue', 'new_feature', 'billing', 'general_question'])],
            'client_priority' => ['required', Rule::in(['normal', 'urgent'])],
            'subject' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:25600'],
        ]);
        $project = isset($data['project_id']) ? Project::findOrFail($data['project_id']) : null;
        abort_if($project && $project->company_id !== $request->user()->company_id, 404);
        unset($data['file']);
        $supportRequest = SupportRequest::create($data + ['company_id' => $request->user()->company_id, 'created_by' => $request->user()->id]);
        $this->attach($request, $supportRequest, "requests/{$supportRequest->id}");
        app(NotificationService::class)->send(
            User::where('role', 'owner')->get(), 'request_created', 'action_required',
            'New client request', "#{$supportRequest->id} {$supportRequest->subject}", route('owner.requests.show', $supportRequest)
        );

        return redirect()->route('client.requests.show', $supportRequest)->with('success', 'Request submitted.');
    }

    public function show(Request $request, SupportRequest $supportRequest): View
    {
        $this->authorize('view', $supportRequest);
        $supportRequest->load(['project', 'attachments', 'messages' => fn ($query) => $query->where('is_internal', false), 'messages.user', 'messages.attachments']);

        return view('client.requests.show', compact('supportRequest'));
    }

    public function message(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorize('update', $supportRequest);
        abort_if($supportRequest->status === 'closed', 422);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'file' => ['nullable', 'file', 'max:25600']]);
        $message = $supportRequest->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_internal' => false]);
        $this->attach($request, $message, "requests/{$supportRequest->id}/messages");
        if ($supportRequest->status === 'waiting_for_client') {
            $supportRequest->update(['status' => 'in_progress']);
        }
        app(NotificationService::class)->send(
            User::where('role', 'owner')->get(), 'request_message', 'important_update',
            'New request message', "Client replied to #{$supportRequest->id} {$supportRequest->subject}", route('owner.requests.show', $supportRequest)
        );

        return back()->with('success', 'Message sent.');
    }

    private function attach(Request $request, mixed $attachable, string $directory): void
    {
        if (! $request->hasFile('file')) {
            return;
        }
        $file = $request->file('file');
        $path = $file->store($directory, 'local');
        $attachable->attachments()->create([
            'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
        ]);
    }
}
