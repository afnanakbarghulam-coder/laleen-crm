<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiContentEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentEntryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        KpiContentEntry::create($validated + ['created_by' => auth()->id()]);

        return redirect()->route('kpi.content.index')->with('success', 'Content calendar entry logged.');
    }

    public function update(Request $request, KpiContentEntry $contentEntry)
    {
        $contentEntry->update($this->validated($request, $contentEntry));

        return redirect()->route('kpi.content.index')->with('success', 'Content calendar entry updated.');
    }

    public function destroy(KpiContentEntry $contentEntry)
    {
        $contentEntry->delete();

        return redirect()->route('kpi.content.index')->with('success', 'Content calendar entry deleted.');
    }

    private function validated(Request $request, ?KpiContentEntry $contentEntry = null): array
    {
        return $request->validate([
            'creator_name' => 'required|string|max:100',
            'entry_date' => [
                'required',
                'date',
                Rule::unique('kpi_content_entries', 'entry_date')
                    ->where('creator_name', $request->creator_name)
                    ->ignore($contentEntry?->id),
            ],
            'activity_type' => 'nullable|string|max:100',
            'feed_post_schedule' => 'nullable|string|max:255',
            'story_theme' => 'nullable|string|max:255',
            'story_flow' => 'nullable|string|max:1000',
            'feed_posted' => ['required', Rule::in(['Y', 'N'])],
            'standards_feed' => ['required', Rule::in(['Y', 'N', 'NA'])],
            'standards_stories' => ['required', Rule::in(['Y', 'N', 'NA'])],
            'issues' => 'nullable|string|max:255',
        ], [
            'entry_date.unique' => 'This creator already has an entry logged for that date.',
        ]);
    }
}
