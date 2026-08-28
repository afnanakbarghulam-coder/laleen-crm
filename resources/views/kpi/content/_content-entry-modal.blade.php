{{-- Shared Add/Edit modal for Content Calendar rows. JS toggles it between
     "create" (form posts to content-entries.store) and "edit" (posts to
     content-entries.update for the clicked row). --}}
<div class="modal fade" id="contentEntryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" id="contentEntryForm" action="{{ route('kpi.content-entries.store') }}">
                @csrf
                <input type="hidden" name="_method" id="contentEntryFormMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="contentEntryModalTitle">Add Calendar Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Creator</label>
                            <input type="text" name="creator_name" id="contentEntryCreator" class="form-control" list="contentEntryCreatorList" required maxlength="100">
                            <datalist id="contentEntryCreatorList">
                                @foreach ($creators as $c)
                                    <option value="{{ $c }}">
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="entry_date" id="contentEntryDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Activity Type</label>
                            <input type="text" name="activity_type" id="contentEntryActivityType" class="form-control" list="contentEntryActivityTypeList" placeholder="e.g. Feed Post" maxlength="100">
                            <datalist id="contentEntryActivityTypeList">
                                @foreach (\App\Models\KpiContentEntry::ACTIVITY_TYPE_SUGGESTIONS as $t)
                                    <option value="{{ $t }}">
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Feed Post / Shoot Schedule</label>
                            <input type="text" name="feed_post_schedule" id="contentEntryFeedSchedule" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Story Theme</label>
                            <input type="text" name="story_theme" id="contentEntryStoryTheme" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Story Flow</label>
                            <input type="text" name="story_flow" id="contentEntryStoryFlow" class="form-control" maxlength="1000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Feed Post Posted?</label>
                            <select name="feed_posted" id="contentEntryFeedPosted" class="form-select" required>
                                <option value="N">No</option>
                                <option value="Y">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standards Met — Feed</label>
                            <select name="standards_feed" id="contentEntryStandardsFeed" class="form-select" required>
                                <option value="NA">N/A</option>
                                <option value="Y">Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standards Met — Stories</label>
                            <select name="standards_stories" id="contentEntryStandardsStories" class="form-select" required>
                                <option value="NA">N/A</option>
                                <option value="Y">Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Issues <span class="text-muted">(optional)</span></label>
                            <input type="text" name="issues" id="contentEntryIssues" class="form-control" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.resetContentEntryForm = function () {
        document.getElementById('contentEntryModalTitle').innerText = 'Add Calendar Entry';
        document.getElementById('contentEntryForm').action = '{{ route('kpi.content-entries.store') }}';
        document.getElementById('contentEntryFormMethod').value = '';
        document.getElementById('contentEntryForm').reset();
        document.getElementById('contentEntryDate').value = new Date().toISOString().slice(0, 10);
    };

    window.editContentEntry = function (entry) {
        resetContentEntryForm();

        document.getElementById('contentEntryModalTitle').innerText = 'Edit Calendar Entry';
        document.getElementById('contentEntryForm').action = `/kpis/content-entries/${entry.id}`;
        document.getElementById('contentEntryFormMethod').value = 'PUT';

        document.getElementById('contentEntryCreator').value = entry.creator_name || '';
        document.getElementById('contentEntryDate').value = entry.entry_date ? entry.entry_date.slice(0, 10) : '';
        document.getElementById('contentEntryActivityType').value = entry.activity_type || '';
        document.getElementById('contentEntryFeedSchedule').value = entry.feed_post_schedule || '';
        document.getElementById('contentEntryStoryTheme').value = entry.story_theme || '';
        document.getElementById('contentEntryStoryFlow').value = entry.story_flow || '';
        document.getElementById('contentEntryFeedPosted').value = entry.feed_posted || 'N';
        document.getElementById('contentEntryStandardsFeed').value = entry.standards_feed || 'NA';
        document.getElementById('contentEntryStandardsStories').value = entry.standards_stories || 'NA';
        document.getElementById('contentEntryIssues').value = entry.issues || '';

        new bootstrap.Modal(document.getElementById('contentEntryModal')).show();
    };

    document.querySelectorAll('.content-entry-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editContentEntry(JSON.parse(this.dataset.entry));
        });
    });
</script>
