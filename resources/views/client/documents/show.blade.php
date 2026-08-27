<x-layouts.app :title="($currentVersion->snapshot['title'] ?? $document->title).' - Ikira Portal'">
    @php($isCurrent = $currentVersion->version === $document->current_version)
    <div class="mb-7 flex flex-wrap justify-between gap-4"><div><p class="text-sm font-medium text-indigo-600">{{ $document->document_number }} · Version {{ $currentVersion->version }}</p><h1 class="mt-1 text-3xl font-semibold">{{ $currentVersion->snapshot['title'] ?? $document->title }}</h1><p class="mt-2 text-slate-500">{{ $isCurrent && $document->status !== 'draft' ? str($document->status)->replace('_',' ')->title() : 'Previously issued version' }}</p></div><a href="{{ route('client.documents.pdf', ['document'=>$document, 'version'=>$currentVersion->version]) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm">Download PDF</a></div>
    <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-6"><div class="document-preview">{!! app(\App\Services\DocumentHtmlService::class)->clean($currentVersion->content) !!}</div></section>
    @if($isCurrent && $document->status === 'awaiting_approval')
        <section class="mt-6 rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            <h2 class="font-semibold">Confirm this exact version</h2>
            <p class="mt-2 text-sm">Review the PDF before deciding. Your portal decision is stored with this version, your account, date, time and IP address. Payment or silence is not confirmation.</p>
            @if($currentVersion->snapshot['minor_items'] ?? null)
                <p class="mt-3 whitespace-pre-wrap text-sm">Agreed minor items and dates:
{{ $currentVersion->snapshot['minor_items'] }}</p>
            @endif
            <form method="POST" action="{{ route('client.documents.decision', $document) }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="version" value="{{ $currentVersion->version }}">
                <label class="block text-sm">Comment<textarea name="comment" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-3" placeholder="Required when requesting changes"></textarea></label>
                <label class="flex items-start gap-2 rounded-lg bg-white p-3 text-sm">
                    <input class="mt-1" type="checkbox" name="confirm_intent" value="1">
                    <span>I reviewed this PDF and intend to confirm this exact version. I can download and keep a copy.</span>
                </label>
                <div class="flex flex-wrap gap-3">
                    <button name="decision" value="approved" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">{{ $document->type === 'delivery_confirmation' ? 'Confirm delivery' : 'Confirm this version' }}</button>
                    @if(in_array($document->type, ['delivery_confirmation', 'delivery_acceptance'], true) && filled($currentVersion->snapshot['minor_items'] ?? null))
                        <button name="decision" value="accepted_with_minor_items" class="rounded-lg bg-amber-600 px-4 py-2 text-sm text-white">Confirm with the agreed minor items</button>
                    @endif
                    <button name="decision" value="changes_requested" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">Request changes</button>
                </div>
            </form>
        </section>
    @endif
    @if($isCurrent && $document->requiresSignature() && in_array($document->status,['awaiting_signature','signature_received']))<section class="mt-6 rounded-2xl border border-indigo-200 bg-indigo-50 p-5"><h2 class="font-semibold">Upload the signed PDF</h2><p class="mt-2 text-sm">Download this version, arrange the required signatures, and upload the resulting PDF. Uploading is not a qualified electronic signature or confirmation of complete execution; the provider reviews it.</p><form method="POST" enctype="multipart/form-data" action="{{ route('client.documents.signed', $document) }}" class="mt-4 flex flex-wrap gap-3">@csrf<input type="hidden" name="version" value="{{ $currentVersion->version }}"><input type="file" name="file" accept="application/pdf" required><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Upload for review</button></form></section>@endif
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Issued versions and records</h2><div class="mt-3 space-y-3 text-sm">@foreach($document->versions as $version)@if($version->published_at || ($version->version === $document->current_version && !in_array($document->status,['draft','void'])))<div><a class="text-indigo-600" href="{{ route('client.documents.show', ['document'=>$document,'version'=>$version->version]) }}">Version {{ $version->version }}</a>@if($version->signed_at) · Execution confirmed @endif @foreach($version->signedAttachments as $attachment)<a class="ml-3 text-indigo-600 underline" href="{{ route('attachments.download',$attachment) }}">{{ $attachment->original_name }}</a>@endforeach</div>@endif @endforeach</div></section>
</x-layouts.app>
