<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        return view('owner.document-templates.index', ['templates' => DocumentTemplate::orderBy('type')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        DocumentTemplate::create($request->validate([
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'], 'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Document template created.');
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->update($request->validate([
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'], 'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Document template updated.');
    }
}
