<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportRequestController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportRequest::with(['company', 'project', 'assignee'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('owner.requests.index', ['requests' => $query->paginate(25)->withQueryString()]);
    }

    public function show(SupportRequest $supportRequest): View
    {
        return view('owner.requests.show', ['supportRequest' => $supportRequest->load([
            'company', 'project', 'creator', 'assignee', 'messages.user', 'messages.attachments',
            'attachments', 'externalCommunications.recorder',
        ]), 'teamUsers' => User::where('role', '!=', 'client')->orderBy('name')->get()]);
    }

    public function update(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'waiting_for_client', 'estimate_sent', 'completed', 'closed'])],
            'internal_priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'billing_classification' => ['nullable', Rule::in(['warranty', 'included_in_care', 'complimentary', 'billable'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'], 'care_minutes_used' => ['nullable', 'integer', 'min:0'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($data, $supportRequest) {
            $isCompleting = $data['status'] === 'completed' && $supportRequest->status !== 'completed';
            $supportRequest->update($data + ['completed_at' => $isCompleting ? now() : $supportRequest->completed_at]);
            if ($isCompleting && $data['billing_classification'] === 'included_in_care' && ! $supportRequest->care_minutes_applied_at) {
                $plan = $supportRequest->project?->carePlans()->where('status', 'active')->first()
                    ?? $supportRequest->company->carePlans()->where('status', 'active')->first();
                if ($plan) {
                    $minutes = (int) ($data['care_minutes_used'] ?? 0);
                    $plan->increment('used_support_minutes', $minutes);
                    $plan->activities()->create([
                        'recorded_by' => auth()->id(), 'type' => 'support', 'minutes' => $minutes,
                        'notes' => "Request #{$supportRequest->id}: {$supportRequest->subject}", 'occurred_at' => now(),
                    ]);
                    $supportRequest->update(['care_minutes_applied_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Request updated.');
    }

    public function message(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'is_internal' => ['nullable', 'boolean'], 'file' => ['nullable', 'file', 'max:25600']]);
        $message = $supportRequest->messages()->create([
            'user_id' => $request->user()->id, 'body' => $data['body'], 'is_internal' => $request->boolean('is_internal'),
        ]);
        $this->attach($request, $message, "requests/{$supportRequest->id}/messages");
        if (! $message->is_internal) {
            app(NotificationService::class)->send(
                User::where('company_id', $supportRequest->company_id)->get(), 'request_message', 'important_update',
                'New request message', "Ikira replied to #{$supportRequest->id} {$supportRequest->subject}", route('client.requests.show', $supportRequest), false
            );
        }

        return back()->with('success', 'Message added.');
    }

    public function external(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'instagram', 'whatsapp', 'phone', 'meeting', 'other'])],
            'summary' => ['required', 'string', 'max:10000'], 'occurred_at' => ['required', 'date'],
        ]);
        $supportRequest->externalCommunications()->create($data + [
            'company_id' => $supportRequest->company_id, 'project_id' => $supportRequest->project_id, 'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'External communication logged.');
    }

    public function changeOrder(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        abort_unless($supportRequest->billing_classification === 'billable', 422);
        $data = $request->validate(['price' => ['required', 'numeric', 'min:0'], 'content' => ['nullable', 'string']]);
        $document = DB::transaction(function () use ($supportRequest, $data, $request) {
            $document = Document::create([
                'company_id' => $supportRequest->company_id, 'project_id' => $supportRequest->project_id,
                'type' => 'change_order', 'title' => "Change Order — {$supportRequest->subject}", 'status' => 'draft',
            ]);
            $content = $data['content'] ?: '<h2>Requested change</h2><p>'.e($supportRequest->description).'</p><h3>Additional fee</h3><p>'.$supportRequest->project?->currency.' '.number_format((float) $data['price'], 2).'</p>';
            $document->versions()->create(['version' => 1, 'content' => $content, 'created_by' => $request->user()->id]);
            $supportRequest->update(['status' => 'estimate_sent']);

            return $document;
        });

        return redirect()->route('owner.documents.show', $document)->with('success', 'Change Order draft created.');
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
