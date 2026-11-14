@php
    use App\Models\CrmIdea;

    $ideasEditPayload = $ideas->mapWithKeys(function (CrmIdea $idea) {
        return [
            $idea->id => [
                'id' => (int) $idea->id,
                'title' => (string) $idea->title,
                'category' => (string) $idea->category,
                'description' => (string) $idea->description,
                'status' => (string) $idea->status,
                'update_url' => route('guide.ideas.update', $idea),
                'convert_url' => route('guide.ideas.convert', $idea),
            ],
        ];
    });
@endphp

<div class="gd-ideas-hub" id="gdIdeasHub">
    {{-- FILTER PILLS + SUBMIT IDEA BUTTON --}}
    <div class="gd-subbar gd-no-print mb-3">
        <div class="gd-cat-bar" role="tablist" aria-label="Idea Categories">
            <button type="button" class="gd-cat-pill active" data-idea-cat-btn="all">All</button>
            @foreach ($ideaCategories as $cat)
                <button type="button" class="gd-cat-pill" data-idea-cat-btn="{{ $cat }}">{{ $cat }}</button>
            @endforeach
        </div>

        <button type="button" class="gd-action-btn" id="gdNewIdeaBtn">
            <i class="bx bx-bulb me-1"></i> + Submit Idea
        </button>
    </div>

    <div class="gd-status-filter-row gd-no-print mb-4">
        <span class="gd-preview-label">Status:</span>
        <button type="button" class="gd-preview-pill active" data-idea-status-btn="all">All Statuses</button>
        @foreach ($ideaStatuses as $st)
            <button type="button" class="gd-preview-pill" data-idea-status-btn="{{ $st }}">{{ $st }}</button>
        @endforeach
    </div>

    {{-- IDEAS BOARD --}}
    @if ($ideas->isEmpty())
        <article class="gd-card text-center py-5">
            <h3 class="gd-card-title mb-1">Brainstorm &amp; Ideas Board</h3>
            <p class="gd-track-desc mb-3">Share campaign hooks, Giveaway angles, Reels scripts, branch upsells, or CRM improvements.</p>
            <button type="button" class="gd-action-btn" onclick="document.getElementById('gdNewIdeaBtn')?.click()">
                + Submit First Idea
            </button>
        </article>
    @else
        <div class="gd-cards-stack" id="gdIdeasStack">
            @foreach ($ideas as $idea)
                @php
                    $hasVoted = in_array((int) $idea->id, $votedIdeaIds, true);
                    $statusPillClass = match ($idea->status) {
                        CrmIdea::STATUS_APPROVED => 'gd-pill-yellow',
                        CrmIdea::STATUS_CONVERTED => 'gd-pill-green',
                        CrmIdea::STATUS_ARCHIVED => 'gd-pill-muted',
                        default => 'gd-pill-neutral',
                    };
                    $canModifyThis = (!$isPreviewMode || !empty($canAdminIdeas)) && $idea->canBeModifiedBy(auth()->user());
                    $commentsCount = $idea->comments->count();
                @endphp

                <article class="gd-card gd-idea-card"
                         data-idea-card
                         data-idea-category="{{ $idea->category }}"
                         data-idea-status="{{ $idea->status }}"
                         id="crm-idea-{{ $idea->id }}">
                    <div class="gd-card-head">
                        <div class="gd-card-head-left">
                            <span class="gd-card-orb">
                                <i class="bx bx-bulb"></i>
                            </span>
                            <div>
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                    <span class="gd-target-tag">{{ $idea->category }}</span>
                                    <span class="{{ $statusPillClass }}">{{ $idea->status }}</span>
                                    @if ($idea->opsTask)
                                        <span class="gd-pill-green">
                                            Ops Task &middot; Pending: {{ $idea->opsTask->pending_with_name }}
                                            @if ($idea->opsTask->due_at)
                                                &middot; Due {{ $idea->opsTask->due_at->format('d M, H:i') }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <h3 class="gd-card-title mb-0">{{ $idea->title }}</h3>
                            </div>
                        </div>

                        <div class="gd-card-actions gd-no-print">
                            <form method="POST" action="{{ route('guide.ideas.vote', $idea) }}" class="d-inline">
                                @csrf
                                <button type="submit"
                                        class="gd-upvote-btn {{ $hasVoted ? 'voted' : '' }}"
                                        title="{{ $hasVoted ? 'Remove upvote' : 'Upvote idea' }}">
                                    <i class="bx {{ $hasVoted ? 'bxs-up-arrow' : 'bx-up-arrow' }}"></i>
                                    <span>Upvote</span>
                                    <span class="gd-upvote-count">{{ $idea->upvotes_count }}</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <p class="gd-idea-desc mb-2">{{ $idea->description }}</p>

                    <div class="gd-muted-caption mb-1">
                        Submitted by <strong style="color: var(--gd-cream);">{{ $idea->author?->name ?? 'Team Member' }}</strong>
                        &middot; {{ $idea->created_at?->diffForHumans() ?? 'recently' }}
                    </div>

                    <div class="gd-guardrail-row gd-no-print">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button"
                                    class="gd-ghost-btn"
                                    data-toggle-comments="{{ $idea->id }}">
                                <i class="bx bx-message-rounded-dots"></i>
                                <span>Comments ({{ $commentsCount }})</span>
                            </button>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            @if (!empty($canAdminIdeas))
                                @if ($idea->status === CrmIdea::STATUS_NEW)
                                    <form method="POST" action="{{ route('guide.ideas.status', $idea) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ CrmIdea::STATUS_APPROVED }}">
                                        <button type="submit" class="gd-ghost-btn">Approve</button>
                                    </form>
                                @endif

                                @if ($idea->status !== CrmIdea::STATUS_CONVERTED)
                                    <button type="button"
                                            class="gd-action-btn"
                                            data-convert-idea="{{ $idea->id }}">
                                        <i class="bx bx-transfer-alt me-1"></i> Convert to Ops Task
                                    </button>
                                @endif
                            @endif

                            @if ($canModifyThis)
                                <button type="button" class="gd-ghost-btn" data-edit-idea="{{ $idea->id }}">
                                    Edit
                                </button>
                                <form method="POST"
                                      action="{{ route('guide.ideas.destroy', $idea) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Remove this idea?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="gd-danger-btn">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- EXPANDABLE QUICK COMMENTS THREAD --}}
                    <div class="gd-comments-drawer d-none gd-no-print" id="gdCommentsDrawer-{{ $idea->id }}">
                        @if ($idea->comments->isNotEmpty())
                            <div class="gd-comments-list mb-3">
                                @foreach ($idea->comments as $comment)
                                    <div class="gd-comment-row">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span class="gd-comment-author">{{ $comment->user?->name ?? 'Team Member' }}</span>
                                            <span class="gd-muted-caption">{{ $comment->created_at?->diffForHumans() }}</span>
                                        </div>
                                        <div class="gd-comment-body">{{ $comment->body }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('guide.ideas.comments.store', $idea) }}" class="gd-comment-form">
                            @csrf
                            <input type="text"
                                   name="body"
                                   class="gd-form-control"
                                   required
                                   maxlength="2000"
                                   placeholder="Add feedback, hook variation, or branch execution note...">
                            <button type="submit" class="gd-action-btn">Post</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="gd-card text-center py-4 d-none" id="gdEmptyIdeasFilter">
            <p class="gd-track-desc mb-0">No ideas match the selected category or status filter.</p>
        </div>
    @endif
</div>

{{-- + SUBMIT / EDIT IDEA MODAL (ALL AUTHENTICATED GUIDE USERS) --}}
<div class="gd-modal-backdrop d-none gd-no-print" id="gdIdeaModal" role="dialog" aria-modal="true" aria-labelledby="gdIdeaModalTitle">
    <div class="gd-modal-dialog">
        <form method="POST" action="{{ route('guide.ideas.store') }}" id="gdIdeaForm">
            @csrf
            <input type="hidden" name="_method" value="POST" id="gdIdeaFormMethod">

            <div class="gd-modal-header">
                <h3 class="gd-card-title mb-0" id="gdIdeaModalTitle">Submit Campaign or CRM Idea</h3>
                <button type="button" class="gd-modal-close" data-close-modal="gdIdeaModal">&times;</button>
            </div>

            <div class="gd-modal-body">
                <div class="mb-3">
                    <label class="gd-form-label" for="gdIdeaTitleInput">Idea Title</label>
                    <input type="text" name="title" id="gdIdeaTitleInput" class="gd-form-control" required minlength="3" maxlength="255" placeholder="e.g., Bestie Bridal Glow QR Card at Wakrah Reception">
                </div>

                <div class="mb-3">
                    <label class="gd-form-label" for="gdIdeaCategoryInput">Category</label>
                    <select name="category" id="gdIdeaCategoryInput" class="gd-form-control" required>
                        @foreach ($ideaCategories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                @if (!empty($canAdminIdeas))
                    <div class="mb-3 d-none" id="gdIdeaStatusFieldWrap">
                        <label class="gd-form-label" for="gdIdeaStatusInput">Status</label>
                        <select name="status" id="gdIdeaStatusInput" class="gd-form-control">
                            @foreach ($ideaStatuses as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="mb-1">
                    <label class="gd-form-label" for="gdIdeaDescInput">Description &amp; Expected Impact</label>
                    <textarea name="description" id="gdIdeaDescInput" rows="4" class="gd-form-control" required minlength="3" maxlength="4000" placeholder="Outline the hook, offer angle, target branch, or workflow improvement..."></textarea>
                </div>
            </div>

            <div class="gd-modal-footer">
                <button type="button" class="gd-ghost-btn" data-close-modal="gdIdeaModal">Cancel</button>
                <button type="submit" class="gd-action-btn" id="gdIdeaSubmitBtn">Submit Idea</button>
            </div>
        </form>
    </div>
</div>

{{-- CONVERT IDEA TO OPS TRACKER MODAL (STRICTLY REAL ADMIN ONLY) --}}
@if (!empty($canAdminIdeas))
    <div class="gd-modal-backdrop d-none gd-no-print" id="gdConvertOpsModal" role="dialog" aria-modal="true" aria-labelledby="gdConvertModalTitle">
        <div class="gd-modal-dialog">
            <form method="POST" action="" id="gdConvertOpsForm">
                @csrf
                <div class="gd-modal-header">
                    <h3 class="gd-card-title mb-0" id="gdConvertModalTitle">Convert Idea to Ops Task</h3>
                    <button type="button" class="gd-modal-close" data-close-modal="gdConvertOpsModal">&times;</button>
                </div>

                <div class="gd-modal-body">
                    <div class="mb-3 pb-3" style="border-bottom: 1px solid var(--gd-hairline);">
                        <span class="gd-target-tag mb-1 d-inline-block" id="gdConvertIdeaCategory">Giveaway</span>
                        <div class="gd-card-title" id="gdConvertIdeaTitle">Idea Title</div>
                    </div>

                    <div class="mb-3">
                        <label class="gd-form-label">Pending With (Select Team Assignees)</label>
                        <div class="gd-assignee-grid">
                            @foreach ($opsAssigneeUsers as $uOpt)
                                <label class="gd-assignee-chip">
                                    <input type="checkbox" name="assigned_user_ids[]" value="{{ $uOpt['id'] }}">
                                    <span>{{ $uOpt['name'] }}</span>
                                    <span class="gd-muted-caption">({{ $uOpt['role_label'] }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="gd-form-label" for="gdConvertTimeframe">Timeframe (From Now)</label>
                            <select name="timeframe" id="gdConvertTimeframe" class="gd-form-control" required>
                                <option value="24h">24h (+24 Hours from now)</option>
                                <option value="48h" selected>48h (+48 Hours from now)</option>
                                <option value="72h">3 Days (+72 Hours from now)</option>
                                <option value="168h">1 Week (+7 Days from now)</option>
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <label class="gd-form-label" for="gdConvertPriority">Priority</label>
                            <select name="priority" id="gdConvertPriority" class="gd-form-control">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="gd-modal-footer">
                    <button type="button" class="gd-ghost-btn" data-close-modal="gdConvertOpsModal">Cancel</button>
                    <button type="submit" class="gd-action-btn">Create Ops Task</button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
    window.__laleenIdeasMap = {{ Js::from($ideasEditPayload) }};
</script>
