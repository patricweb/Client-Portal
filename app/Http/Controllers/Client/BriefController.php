<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BriefController extends Controller
{
    public function edit(Request $request, Project $project): View
    {
        $this->authorizeCompany($request, $project);
        $brief = $project->brief()->with(['template.fields', 'answers'])->firstOrFail();

        return view('client.brief.edit', compact('project', 'brief'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeCompany($request, $project);
        $brief = $project->brief()->with('template.fields')->firstOrFail();
        abort_unless(in_array($brief->status, ['draft', 'needs_clarification'], true), 403);

        $answers = $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string', 'max:10000']])['answers'] ?? [];
        foreach ($brief->template?->fields ?? [] as $field) {
            $brief->answers()->updateOrCreate(
                ['brief_template_field_id' => $field->id],
                ['value' => $answers[$field->id] ?? null]
            );
        }

        if ($request->boolean('submit')) {
            $brief->load(['template.fields', 'answers']);
            $answerMap = $brief->answers->pluck('value', 'brief_template_field_id');
            foreach ($brief->template?->fields->where('is_required', true) ?? [] as $field) {
                if (blank($answerMap->get($field->id))) {
                    return back()->withErrors(["answers.{$field->id}" => "{$field->label} is required."])->withInput();
                }
            }

            $brief->update(['status' => 'submitted', 'submitted_at' => now()]);
            if ($project->status === 'awaiting_brief') {
                $project->update(['status' => 'awaiting_contract']);
            }

            return redirect()->route('client.projects.show', $project)->with('success', 'Brief submitted to Ikira.');
        }

        return back()->with('success', 'Brief draft saved.');
    }

    private function authorizeCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id === $request->user()->company_id, 404);
    }
}
