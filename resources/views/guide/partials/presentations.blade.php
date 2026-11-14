@php
    $presentationsPayload = $presentations->map(function ($deck) {
        return [
            'id' => (int) $deck->id,
            'title' => (string) $deck->title,
            'slug' => (string) $deck->slug,
            'category' => (string) $deck->category,
            'summary' => (string) ($deck->summary ?? ''),
            'is_pinned' => (bool) $deck->is_pinned,
            'slides' => is_array($deck->slides) ? array_values($deck->slides) : [],
            'update_url' => route('guide.presentations.update', $deck),
        ];
    })->values();

    $hasDeckJsonError = $errors->has('deck_json');
@endphp

<div class="gd-pres-hub" id="gdPresentationsHub" data-open-import-on-load="{{ $hasDeckJsonError ? '1' : '0' }}">
    {{-- FILTER BAR + NEW PRESENTATION TRIGGER --}}
    <div class="gd-subbar gd-no-print mb-4">
        <div class="gd-cat-bar" role="tablist" aria-label="Presentation Categories">
            <button type="button" class="gd-cat-pill active" data-pres-cat="all">All Decks</button>
            @foreach ($presentationCategories as $cat)
                <button type="button" class="gd-cat-pill" data-pres-cat="{{ $cat }}">{{ $cat }}</button>
            @endforeach
        </div>

        @if (!empty($canManagePresentations))
            <button type="button" class="gd-action-btn" id="gdNewPresentationBtn">
                <span>+ New Presentation</span>
            </button>
        @endif
    </div>

    {{-- 16:9 INTERACTIVE SLIDE DECK PLAYER STAGE (6 VISUAL CONSULTING LAYOUTS) --}}
    <section class="gd-player-shell d-none mb-4" id="gdDeckPlayerShell" aria-label="Slide Deck Player">
        <div class="gd-player-topbar">
            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                <span class="gd-target-tag" id="gdPlayerCategoryBadge">Growth Playbook</span>
                <span class="gd-pill-neutral" id="gdPlayerLayoutBadge">Cover</span>
                <h2 class="gd-player-deck-title mb-0 text-truncate" id="gdPlayerDeckTitle">Presentation</h2>
            </div>

            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <span class="gd-slide-counter" id="gdSlideIndicator">Slide 1 of 12</span>
                <button type="button" class="gd-ghost-btn" id="gdToggleFullscreenBtn" title="Toggle Fullscreen">
                    <i class="bx bx-fullscreen"></i>
                    <span>Fullscreen</span>
                </button>
                <button type="button" class="gd-ghost-btn" id="gdClosePlayerBtn" title="Close Player (Esc)">
                    <i class="bx bx-x"></i>
                    <span>Close</span>
                </button>
            </div>
        </div>

        <div class="gd-player-progress-track" aria-hidden="true">
            <div class="gd-player-progress-fill" id="gdSlideProgressFill" style="width: 8.33%;"></div>
        </div>

        <div class="gd-slide-stage" id="gdSlideStage" tabindex="0">
            <div class="gd-slide-canvas" id="gdSlideCanvas"></div>
            <div class="gd-slide-footer d-none" id="gdSlideFooter"></div>
        </div>

        <div class="gd-player-controls">
            <button type="button" class="gd-ghost-btn" id="gdPrevSlideBtn">
                <i class="bx bx-chevron-left"></i>
                <span>Prev</span>
            </button>

            <div class="gd-slide-dots" id="gdSlideDots" role="navigation" aria-label="Slide selector"></div>

            <button type="button" class="gd-action-btn" id="gdNextSlideBtn">
                <span>Next</span>
                <i class="bx bx-chevron-right ms-1"></i>
            </button>
        </div>
    </section>

    {{-- PRESENTATIONS VAULT GRID --}}
    @if ($presentations->isEmpty())
        <article class="gd-card text-center py-5">
            <h3 class="gd-card-title mb-1">No presentations yet</h3>
            <p class="gd-track-desc mb-0">Create a strategy playbook or paste a Deck JSON payload to publish immediately.</p>
        </article>
    @else
        <div class="gd-cards-stack" id="gdPresentationsGrid">
            @foreach ($presentations as $deck)
                @php
                    $slidesList = is_array($deck->slides) ? $deck->slides : [];
                    $slideTotal = count($slidesList);
                @endphp
                <article class="gd-card gd-pres-card" data-pres-card data-pres-category="{{ $deck->category }}">
                    <div class="gd-pres-row">
                        <div class="gd-pres-main">
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                @if ($deck->is_pinned)
                                    <span class="gd-scope-pill gd-scope-champagne">Pinned</span>
                                @endif
                                <span class="gd-target-tag">{{ $deck->category }}</span>
                                <span class="gd-pill-neutral">{{ $slideTotal }} {{ $slideTotal === 1 ? 'Slide' : 'Slides' }}</span>
                            </div>

                            <h3 class="gd-card-title mb-1">{{ $deck->title }}</h3>

                            @if (!empty($deck->summary))
                                <p class="gd-pres-summary mb-0">{{ $deck->summary }}</p>
                            @endif
                        </div>

                        <div class="gd-pres-actions gd-no-print">
                            <button type="button" class="gd-action-btn" data-open-deck="{{ $deck->id }}">
                                <i class="bx bx-play-circle me-1"></i> Present Deck
                            </button>

                            @if (!empty($canManagePresentations))
                                <button type="button" class="gd-ghost-btn" data-edit-deck="{{ $deck->id }}">
                                    Edit
                                </button>
                            @endif

                            @if (!empty($canDeletePresentations))
                                <form method="POST"
                                      action="{{ route('guide.presentations.destroy', $deck) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this presentation deck?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="gd-danger-btn">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>

{{-- CREATE / EDIT / IMPORT PRESENTATION MODAL (ADMIN & MANAGER ONLY) --}}
@if (!empty($canManagePresentations))
    <div class="gd-modal-backdrop {{ $hasDeckJsonError ? '' : 'd-none' }} gd-no-print" id="gdPresentationModal" role="dialog" aria-modal="true" aria-labelledby="gdPresModalTitle">
        <div class="gd-modal-dialog gd-modal-lg">
            <div class="gd-modal-header">
                <h3 class="gd-card-title mb-0" id="gdPresModalTitle">New Strategy Presentation</h3>
                <button type="button" class="gd-modal-close" data-close-modal="gdPresentationModal">&times;</button>
            </div>

            {{-- MODE SWITCHER TABS (VISIBLE ON NEW PRESENTATION) --}}
            <div class="px-4 pt-3 {{ $hasDeckJsonError ? '' : '' }}" id="gdPresModeTabsWrap">
                <div class="gd-top-subtabs">
                    <button type="button"
                            class="gd-subtab-btn {{ $hasDeckJsonError ? '' : 'active' }}"
                            data-pres-mode-btn="manual">
                        <i class="bx bx-slider-alt"></i>
                        <span>Visual Slide Builder</span>
                    </button>
                    <button type="button"
                            class="gd-subtab-btn {{ $hasDeckJsonError ? 'active' : '' }}"
                            data-pres-mode-btn="json">
                        <i class="bx bx-code-curly"></i>
                        <span>Paste Deck JSON (Instant Import)</span>
                    </button>
                </div>
            </div>

            {{-- TAB 1: VISUAL SLIDE BUILDER (CREATE & EDIT) --}}
            <form method="POST"
                  action="{{ route('guide.presentations.store') }}"
                  id="gdPresentationForm"
                  class="{{ $hasDeckJsonError ? 'd-none' : '' }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="gdPresentationMethod">

                <div class="gd-modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="gd-form-label" for="gdPresTitleInput">Deck Title</label>
                            <input type="text" name="title" id="gdPresTitleInput" class="gd-form-control" required maxlength="255" placeholder="e.g., Ramadan Bridal Transformation Playbook">
                        </div>
                        <div class="col-md-5">
                            <label class="gd-form-label" for="gdPresCategoryInput">Category</label>
                            <select name="category" id="gdPresCategoryInput" class="gd-form-control" required>
                                @foreach ($presentationCategories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="gd-form-label" for="gdPresSummaryInput">Executive Summary</label>
                            <input type="text" name="summary" id="gdPresSummaryInput" class="gd-form-control" maxlength="1000" placeholder="One-line campaign goal, branches covered, and core outcome...">
                        </div>
                        <div class="col-12">
                            <label class="gd-check-label">
                                <input type="checkbox" name="is_pinned" value="1" id="gdPresPinnedInput">
                                <span>Pin deck to top of Presentations vault</span>
                            </label>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2 pt-2" style="border-top: 1px solid var(--gd-hairline);">
                        <span class="gd-form-label mb-0">Slides (<span id="gdRepeaterCount">1</span>)</span>
                        <button type="button" class="gd-ghost-btn" id="gdAddSlideRowBtn">
                            <span>+ Add Slide</span>
                        </button>
                    </div>

                    <div id="gdSlidesRepeater" class="gd-repeater-stack"></div>
                </div>

                <div class="gd-modal-footer">
                    <button type="button" class="gd-ghost-btn" data-close-modal="gdPresentationModal">Cancel</button>
                    <button type="submit" class="gd-action-btn" id="gdPresSubmitBtn">Save Presentation</button>
                </div>
            </form>

            {{-- TAB 2: PASTE DECK JSON (INSTANT IMPORT) --}}
            <form method="POST"
                  action="{{ route('guide.presentations.import') }}"
                  id="gdImportDeckJsonForm"
                  class="{{ $hasDeckJsonError ? '' : 'd-none' }}">
                @csrf
                <div class="gd-modal-body">
                    @if ($hasDeckJsonError)
                        <div class="gd-json-error-alert mb-3">
                            @foreach ($errors->get('deck_json') as $errMsg)
                                <div>&bull; {{ $errMsg }}</div>
                            @endforeach
                        </div>
                    @endif

                    <label class="gd-form-label" for="gdDeckJsonTextarea">Deck JSON Payload</label>
                    <p class="gd-muted-caption mb-2">
                        Paste a valid JSON deck object (<code style="color: var(--gd-rose);">title</code>, <code style="color: var(--gd-rose);">category</code>, <code style="color: var(--gd-rose);">summary</code>, and <code style="color: var(--gd-rose);">slides</code> using <code style="color: var(--gd-champagne);">cover</code>, <code style="color: var(--gd-champagne);">big_metric</code>, <code style="color: var(--gd-champagne);">timeline</code>, <code style="color: var(--gd-champagne);">comparison</code>, <code style="color: var(--gd-champagne);">cards</code>, or <code style="color: var(--gd-champagne);">table</code>).
                    </p>
                    <textarea name="deck_json"
                              id="gdDeckJsonTextarea"
                              rows="11"
                              class="gd-form-control gd-code-textarea"
                              required
                              placeholder='{"title":"Al Wakrah Bridal Upsell Playbook","category":"Branch SOP","summary":"Chair-side consultation & combo upgrades.","is_pinned":false,"slides":[{"layout":"cover","eyebrow":"BRANCH SOP","title":"Al Wakrah Bridal Upsell Playbook","subtitle":"3-step chair consultation lifting average ticket size."}]}'>{{ old('deck_json') }}</textarea>
                </div>

                <div class="gd-modal-footer">
                    <button type="button" class="gd-ghost-btn" data-close-modal="gdPresentationModal">Cancel</button>
                    <button type="submit" class="gd-action-btn">Import &amp; Save Deck</button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
    window.__laleenPresentations = {{ Js::from($presentationsPayload) }};
    window.__laleenPresentationLayouts = {{ Js::from($presentationLayouts) }};
</script>
