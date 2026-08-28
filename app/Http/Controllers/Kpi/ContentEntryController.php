<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\KpiContentEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentEntryController extends Controller
{
    /**
     * Every action supports both the inline quick-add/edit grid (AJAX,
     * returns JSON) and a plain form fallback (redirect) — the Content
     * Calendar page only ever uses the AJAX path, but nothing here assumes
     * JS is available.
     */
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $entry = KpiContentEntry::create($validated + ['created_by' => auth()->id()]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Entry added.',
                'entry' => $entry->toEditPayload(),
            ]);
        }

        return redirect()->route('kpi.content.index')->with('success', 'Content calendar entry logged.');
    }

    public function update(Request $request, KpiContentEntry $contentEntry)
    {
        $contentEntry->update($this->validated($request, $contentEntry));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Entry updated.',
                'entry' => $contentEntry->fresh()->toEditPayload(),
            ]);
        }

        return redirect()->route('kpi.content.index')->with('success', 'Content calendar entry updated.');
    }

    public function destroy(Request $request, KpiContentEntry $contentEntry)
    {
        $contentEntry->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Entry deleted.']);
        }

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
            'activity_type' => ['required', Rule::in(KpiContentEntry::ACTIVITY_TYPES)],
            'feed_post_schedule' => 'nullable|string|max:255',
            'stories_posted' => ['required', Rule::in(['Y', 'N'])],
            'feed_posted' => ['required', Rule::in(['Y', 'N'])],
            'standards_stories' => ['required', Rule::in(['Y', 'N'])],
            'standards_feed' => ['required', Rule::in(['Y', 'N'])],
            'event' => ['required', Rule::in(['Y', 'N'])],
            'issues' => 'nullable|string|max:255',
        ], [
            'entry_date.unique' => 'This creator already has an entry logged for that date.',
        ]);
    }
}
