<?php

namespace App\Http\Controllers;

use App\Models\CrmPresentation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmPresentationController extends Controller
{
    public static function canManage(?User $user): bool
    {
        if (!$user || !$user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return strtolower(trim((string) $user->role)) === 'manager';
    }

    public function store(Request $request)
    {
        abort_unless(self::canManage($request->user()), 403);

        $allowedLayouts = array_merge(
            array_keys(CrmPresentation::LAYOUTS),
            array_keys(CrmPresentation::LEGACY_LAYOUT_ALIASES)
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', Rule::in(CrmPresentation::CATEGORIES)],
            'summary' => ['nullable', 'string', 'max:1000'],
            'is_pinned' => ['nullable', 'boolean'],
            'slides' => ['required', 'array', 'min:1', 'max:40'],
            'slides.*.title' => ['required', 'string', 'min:1', 'max:255'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:140'],
            'slides.*.subtitle' => ['nullable', 'string', 'max:400'],
            'slides.*.layout' => ['nullable', 'string', Rule::in($allowedLayouts)],
            'slides.*.highlight_metric' => ['nullable', 'string', 'max:160'],
            'slides.*.body_points' => ['nullable'],
            'slides.*.table_rows_text' => ['nullable', 'string', 'max:8000'],
            'slides.*.table_rows' => ['nullable', 'array'],
            'slides.*.headers' => ['nullable', 'array'],
            'slides.*.rows' => ['nullable', 'array'],
            'slides.*.footer_note' => ['nullable', 'string', 'max:300'],
        ]);

        $cleanTitle = trim(strip_tags($validated['title']));
        $cleanSummary = isset($validated['summary']) ? trim(strip_tags($validated['summary'])) : null;
        $cleanSlides = CrmPresentation::sanitizeSlides($validated['slides']);

        if (empty($cleanSlides)) {
            throw ValidationException::withMessages([
                'slides' => ['Please add at least one slide with content.'],
            ]);
        }

        $presentation = CrmPresentation::create([
            'title' => $cleanTitle,
            'slug' => CrmPresentation::generateUniqueSlug($cleanTitle),
            'category' => $validated['category'],
            'summary' => $cleanSummary !== '' ? $cleanSummary : null,
            'slides' => $cleanSlides,
            'is_pinned' => $request->boolean('is_pinned'),
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'presentation' => $presentation,
            ], 201);
        }

        return redirect()
            ->route('guide.index', ['section' => 'presentations'])
            ->with('success', 'Presentation saved.');
    }

    public function importJson(Request $request)
    {
        abort_unless(self::canManage($request->user()), 403);

        $rawInput = $request->input('deck_json');
        if (!is_string($rawInput) || trim($rawInput) === '') {
            throw ValidationException::withMessages([
                'deck_json' => ['Please paste a valid Deck JSON payload to import.'],
            ]);
        }

        $trimmed = trim($rawInput);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $m)) {
            $trimmed = trim($m[1]);
        }

        if (preg_match('/"(?:contract_pin|base_salary|hourly_wage|pin_hash)"/i', $trimmed)) {
            throw ValidationException::withMessages([
                'deck_json' => ['Deck JSON must never include confidential staff salary, wage, or PIN fields.'],
            ]);
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw ValidationException::withMessages([
                'deck_json' => ['Invalid JSON syntax: ' . json_last_error_msg()],
            ]);
        }

        $allowedLayouts = array_merge(
            array_keys(CrmPresentation::LAYOUTS),
            array_keys(CrmPresentation::LEGACY_LAYOUT_ALIASES)
        );

        $validator = Validator::make($decoded, [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', Rule::in(CrmPresentation::CATEGORIES)],
            'summary' => ['nullable', 'string', 'max:1000'],
            'is_pinned' => ['nullable', 'boolean'],
            'slides' => ['required', 'array', 'min:1', 'max:40'],
            'slides.*.layout' => ['required', 'string', Rule::in($allowedLayouts)],
            'slides.*.title' => ['required', 'string', 'min:1', 'max:255'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:140'],
            'slides.*.subtitle' => ['nullable', 'string', 'max:400'],
            'slides.*.highlight_metric' => ['nullable', 'string', 'max:160'],
            'slides.*.body_points' => ['nullable', 'array'],
            'slides.*.body_points.*' => ['string', 'max:500'],
            'slides.*.headers' => ['nullable', 'array'],
            'slides.*.headers.*' => ['string', 'max:200'],
            'slides.*.rows' => ['nullable', 'array'],
            'slides.*.table_rows' => ['nullable', 'array'],
            'slides.*.footer_note' => ['nullable', 'string', 'max:300'],
        ]);

        if ($validator->fails()) {
            $flatMessages = $validator->errors()->all();
            throw ValidationException::withMessages([
                'deck_json' => $flatMessages,
            ]);
        }

        $validated = $validator->validated();
        $cleanTitle = trim(strip_tags($validated['title']));
        $cleanSummary = isset($validated['summary']) ? trim(strip_tags($validated['summary'])) : null;
        $cleanSlides = CrmPresentation::sanitizeSlides($validated['slides']);

        if (empty($cleanSlides)) {
            throw ValidationException::withMessages([
                'deck_json' => ['Imported JSON must contain at least one valid slide.'],
            ]);
        }

        $presentation = CrmPresentation::create([
            'title' => $cleanTitle,
            'slug' => CrmPresentation::generateUniqueSlug($cleanTitle),
            'category' => $validated['category'],
            'summary' => $cleanSummary !== '' ? $cleanSummary : null,
            'slides' => $cleanSlides,
            'is_pinned' => !empty($validated['is_pinned']),
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'presentation' => $presentation,
            ], 201);
        }

        return redirect()
            ->route('guide.index', ['section' => 'presentations'])
            ->with('success', 'Presentation imported and saved.');
    }

    public function update(Request $request, CrmPresentation $presentation)
    {
        abort_unless(self::canManage($request->user()), 403);

        $allowedLayouts = array_merge(
            array_keys(CrmPresentation::LAYOUTS),
            array_keys(CrmPresentation::LEGACY_LAYOUT_ALIASES)
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', Rule::in(CrmPresentation::CATEGORIES)],
            'summary' => ['nullable', 'string', 'max:1000'],
            'is_pinned' => ['nullable', 'boolean'],
            'slides' => ['required', 'array', 'min:1', 'max:40'],
            'slides.*.title' => ['required', 'string', 'min:1', 'max:255'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:140'],
            'slides.*.subtitle' => ['nullable', 'string', 'max:400'],
            'slides.*.layout' => ['nullable', 'string', Rule::in($allowedLayouts)],
            'slides.*.highlight_metric' => ['nullable', 'string', 'max:160'],
            'slides.*.body_points' => ['nullable'],
            'slides.*.table_rows_text' => ['nullable', 'string', 'max:8000'],
            'slides.*.table_rows' => ['nullable', 'array'],
            'slides.*.headers' => ['nullable', 'array'],
            'slides.*.rows' => ['nullable', 'array'],
            'slides.*.footer_note' => ['nullable', 'string', 'max:300'],
        ]);

        $cleanTitle = trim(strip_tags($validated['title']));
        $cleanSummary = isset($validated['summary']) ? trim(strip_tags($validated['summary'])) : null;
        $existingSlides = is_array($presentation->slides) ? $presentation->slides : [];
        $cleanSlides = CrmPresentation::sanitizeSlides($validated['slides'], $existingSlides);

        if (empty($cleanSlides)) {
            throw ValidationException::withMessages([
                'slides' => ['Please include at least one valid slide.'],
            ]);
        }

        $presentation->update([
            'title' => $cleanTitle,
            'category' => $validated['category'],
            'summary' => $cleanSummary !== '' ? $cleanSummary : null,
            'slides' => $cleanSlides,
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'presentation' => $presentation->fresh(),
            ]);
        }

        return redirect()
            ->route('guide.index', ['section' => 'presentations'])
            ->with('success', 'Presentation updated.');
    }

    public function destroy(Request $request, CrmPresentation $presentation)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $presentation->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('guide.index', ['section' => 'presentations'])
            ->with('success', 'Presentation deleted.');
    }
}
