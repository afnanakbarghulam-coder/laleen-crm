<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdLeadEntry;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdLeadEntryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        AdLeadEntry::create($validated + ['created_by' => auth()->id()]);

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead logged.');
    }

    public function update(Request $request, AdLeadEntry $adLeadEntry)
    {
        $adLeadEntry->update($this->validated($request));

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead updated.');
    }

    public function destroy(AdLeadEntry $adLeadEntry)
    {
        $adLeadEntry->delete();

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'country_code' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'category' => ['required', Rule::in(AdLeadEntry::CATEGORIES)],
            'ticket_amount' => 'nullable|numeric|min:0',
            'branch' => ['nullable', Rule::in(array_keys(AdLeadEntry::BRANCHES))],
            'remarks' => 'nullable|string|max:255',
        ]);

        $phone = Lead::normalizePhone($validated['country_code'], $validated['phone_number']);
        unset($validated['country_code'], $validated['phone_number']);
        $validated['phone'] = $phone;

        $validated['ticket_amount'] = $validated['ticket_amount'] ?? 0;

        return $validated;
    }
}
