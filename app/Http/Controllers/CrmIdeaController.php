<?php

namespace App\Http\Controllers;

use App\Models\CrmIdea;
use App\Models\CrmIdeaComment;
use App\Models\CrmIdeaVote;
use App\Models\OpsActionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CrmIdeaController extends Controller
{
    public const TIMEFRAME_MAP = [
        '24' => 24,
        '24h' => 24,
        '48' => 48,
        '48h' => 48,
        '72' => 72,
        '72h' => 72,
        '3d' => 72,
        '3_days' => 72,
        '3 days' => 72,
        '168' => 168,
        '168h' => 168,
        '1w' => 168,
        '1_week' => 168,
        '1 week' => 168,
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', Rule::in(CrmIdea::CATEGORIES)],
            'description' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        $idea = CrmIdea::create([
            'title' => trim(strip_tags($validated['title'])),
            'category' => $validated['category'],
            'description' => trim(strip_tags($validated['description'])),
            'status' => CrmIdea::STATUS_NEW,
            'upvotes_count' => 0,
            'created_by' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'idea' => $idea->load('author:id,name,role'),
            ], 201);
        }

        return redirect()
            ->route('guide.index', ['section' => 'ideas'])
            ->with('success', 'Idea added to Brainstorm board.');
    }

    public function update(Request $request, CrmIdea $idea)
    {
        $user = $request->user();
        abort_unless($idea->canBeModifiedBy($user), 403);

        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', Rule::in(CrmIdea::CATEGORIES)],
            'description' => ['required', 'string', 'min:3', 'max:4000'],
        ];

        if ($user->isAdmin()) {
            $rules['status'] = ['nullable', 'string', Rule::in(CrmIdea::STATUSES)];
        }

        $validated = $request->validate($rules);

        $payload = [
            'title' => trim(strip_tags($validated['title'])),
            'category' => $validated['category'],
            'description' => trim(strip_tags($validated['description'])),
        ];

        if ($user->isAdmin() && !empty($validated['status'])) {
            $payload['status'] = $validated['status'];
        }

        $idea->update($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'idea' => $idea->fresh(),
            ]);
        }

        return redirect()
            ->route('guide.index', ['section' => 'ideas'])
            ->with('success', 'Idea updated.');
    }

    public function destroy(Request $request, CrmIdea $idea)
    {
        abort_unless($idea->canBeModifiedBy($request->user()), 403);

        $idea->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('guide.index', ['section' => 'ideas'])
            ->with('success', 'Idea removed.');
    }

    public function toggleVote(Request $request, CrmIdea $idea)
    {
        $userId = (int) $request->user()->id;

        [$voted, $count] = DB::transaction(function () use ($idea, $userId) {
            $existing = CrmIdeaVote::where('idea_id', $idea->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                $existing->delete();
                $isVoted = false;
            } else {
                CrmIdeaVote::create([
                    'idea_id' => $idea->id,
                    'user_id' => $userId,
                ]);
                $isVoted = true;
            }

            $freshCount = CrmIdeaVote::where('idea_id', $idea->id)->count();
            $idea->update(['upvotes_count' => $freshCount]);

            return [$isVoted, $freshCount];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'voted' => $voted,
                'upvotes_count' => $count,
            ]);
        }

        return redirect()->route('guide.index', ['section' => 'ideas']);
    }

    public function storeComment(Request $request, CrmIdea $idea)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $cleanBody = trim(strip_tags($validated['body']));
        if ($cleanBody === '') {
            return back()->withErrors(['body' => 'Comment cannot be empty.']);
        }

        $comment = CrmIdeaComment::create([
            'idea_id' => $idea->id,
            'user_id' => $request->user()->id,
            'body' => $cleanBody,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'comment' => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author_name' => $request->user()->name,
                    'created_human' => $comment->created_at?->diffForHumans() ?? 'just now',
                ],
                'comments_count' => $idea->comments()->count(),
            ], 201);
        }

        return redirect()
            ->route('guide.index', ['section' => 'ideas'])
            ->with('success', 'Comment posted.');
    }

    public function updateStatus(Request $request, CrmIdea $idea)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(CrmIdea::STATUSES)],
        ]);

        $idea->update(['status' => $validated['status']]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'idea' => $idea->fresh(),
            ]);
        }

        return redirect()
            ->route('guide.index', ['section' => 'ideas'])
            ->with('success', 'Idea status updated.');
    }

    public function convertToOps(Request $request, CrmIdea $idea)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $rawIds = $request->input('assigned_user_ids', $request->input('pending_with_user_ids', []));
        if (!is_array($rawIds)) {
            $rawIds = $rawIds !== null && $rawIds !== '' ? [$rawIds] : [];
        }
        if (empty($rawIds) && $request->filled('assigned_user_id')) {
            $rawIds = [$request->input('assigned_user_id')];
        }

        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn (int $id) => $id > 0)));

        $request->merge([
            'resolved_user_ids' => $cleanIds,
        ]);

        $rawTimeframe = strtolower(trim((string) $request->input('timeframe', $request->input('sla_hours', '48h'))));
        $slaHours = self::TIMEFRAME_MAP[$rawTimeframe] ?? null;

        $request->validate([
            'resolved_user_ids' => ['required', 'array', 'min:1'],
            'resolved_user_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'priority' => ['nullable', 'string', Rule::in(array_keys(OpsActionItem::PRIORITIES))],
        ]);

        if ($slaHours === null) {
            return back()
                ->withErrors(['timeframe' => 'Please select a valid timeframe (24h, 48h, 3 Days, or 1 Week).'])
                ->withInput();
        }

        $assignees = User::whereIn('id', $cleanIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        if ($assignees->isEmpty()) {
            return back()
                ->withErrors(['assigned_user_ids' => 'Please select at least one active team member.'])
                ->withInput();
        }

        $assignedUserIds = $assignees->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $assignedNames = $assignees->pluck('name')->map(fn ($n) => trim((string) $n))->values()->all();
        $pendingType = OpsActionItem::resolvePrimaryTypeFromNames($assignedNames);
        $pendingName = implode(', ', $assignedNames);
        $priority = in_array($request->input('priority'), array_keys(OpsActionItem::PRIORITIES), true)
            ? (string) $request->input('priority')
            : 'normal';

        $authorName = $idea->author?->name ?? 'Team';
        $notes = trim($idea->description . "\n\n[Converted from Brainstorm & Ideas · {$idea->category} · Submitted by {$authorName}]");

        $opsTask = DB::transaction(function () use (
            $idea,
            $notes,
            $priority,
            $pendingType,
            $pendingName,
            $assignedUserIds,
            $assignedNames,
            $slaHours
        ) {
            $now = now();
            $dueAt = $now->copy()->addHours($slaHours);

            $created = OpsActionItem::create([
                'title' => $idea->title,
                'notes' => $notes,
                'scope' => CrmIdea::opsScopeForCategory($idea->category),
                'impact_lever' => CrmIdea::opsImpactLeverForCategory($idea->category),
                'priority' => $priority,
                'pending_with_type' => $pendingType,
                'pending_with_name' => $pendingName,
                'assigned_user_ids' => $assignedUserIds,
                'assigned_names' => $assignedNames,
                'staff_id' => null,
                'sla_hours' => $slaHours,
                'ball_assigned_at' => $now,
                'due_at' => $dueAt,
                'week_start_date' => $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'is_completed' => false,
            ]);

            $idea->update([
                'status' => CrmIdea::STATUS_CONVERTED,
                'ops_task_id' => $created->id,
            ]);

            return $created;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'idea' => $idea->fresh(),
                'ops_task' => $opsTask->fresh(),
            ]);
        }

        return redirect()
            ->route('guide.index', ['section' => 'ideas'])
            ->with('success', 'Idea converted to an active Ops Tracker task.');
    }
}
