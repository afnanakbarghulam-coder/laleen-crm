@extends('layouts.app')

@section('title', 'Import Clients (CSV)')

@section('content')
<style>
    .import-shell {
        max-width: 820px;
        margin: 0 auto;
    }

    .import-surface {
        background: linear-gradient(180deg, rgba(36, 26, 27, 0.94) 0%, rgba(23, 16, 18, 0.97) 100%);
        border: 1px solid rgba(217, 143, 131, 0.2);
        border-radius: 16px;
        padding: 24px 26px;
        color: #f6ece8;
    }

    .import-step-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #e5b8a8;
        margin-bottom: 6px;
    }

    .import-divider {
        border-top: 1px solid rgba(217, 143, 131, 0.14);
        margin: 22px 0;
    }

    .import-dropzone {
        border: 1.5px dashed rgba(217, 143, 131, 0.34);
        border-radius: 12px;
        background: rgba(15, 11, 12, 0.55);
        padding: 24px 20px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }

    .import-dropzone:hover,
    .import-dropzone.is-dragover {
        border-color: #e5b8a8;
        background: rgba(217, 143, 131, 0.08);
    }

    .import-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: linear-gradient(135deg, #e09b8f 0%, #c97c70 100%);
        color: #1a1210 !important;
        font-weight: 700;
        font-size: 13px;
        padding: 9px 18px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
        transition: filter 0.15s ease, opacity 0.15s ease;
    }

    .import-btn-primary:hover:not(:disabled) {
        filter: brightness(1.06);
    }

    .import-btn-primary:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .import-btn-subtle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(217, 143, 131, 0.24);
        color: #dfcfca;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .import-btn-subtle:hover:not(:disabled) {
        background: rgba(217, 143, 131, 0.14);
        border-color: rgba(231, 154, 145, 0.45);
        color: #fff;
    }

    .import-btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(244, 63, 94, 0.1);
        border: 1px solid rgba(244, 63, 94, 0.32);
        color: #fda4af;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .import-btn-danger:hover:not(:disabled) {
        background: rgba(244, 63, 94, 0.2);
        color: #fff;
    }

    .import-summary-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .import-summary-table th,
    .import-summary-table td {
        padding: 9px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .import-summary-table th {
        color: #b8a29a;
        font-weight: 600;
        text-align: left;
    }

    .import-summary-table td {
        color: #f8efe6;
        font-weight: 700;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .import-progress-track {
        width: 100%;
        height: 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        overflow: hidden;
    }

    .import-progress-fill {
        height: 100%;
        width: 0%;
        border-radius: 999px;
        background: linear-gradient(90deg, #e09b8f 0%, #f1c6b8 100%);
        transition: width 0.2s ease;
    }

    .import-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        background: rgba(217, 143, 131, 0.12);
        border: 1px solid rgba(217, 143, 131, 0.26);
        color: #f1d3c8;
    }

    .import-input-dark {
        background: rgba(15, 11, 12, 0.65) !important;
        border: 1px solid rgba(217, 143, 131, 0.24) !important;
        color: #f6ece8 !important;
        border-radius: 9px !important;
        font-size: 13px !important;
    }
</style>

<div class="import-shell py-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <a href="{{ route('customers.index') }}" class="import-btn-subtle mb-2">
                <i class="bx bx-arrow-back"></i> Back to Clients
            </a>
            <h4 class="fw-bold mb-1" style="color: #f8efe6;">Import Clients (CSV)</h4>
            <div class="small" style="color: #b8a29a;">
                Admin-only E.164 normalization (<code style="color:#e5b8a8;">+974XXXXXXXX</code> / <code style="color:#e5b8a8;">+&lt;cc&gt;&lt;number&gt;</code>) &amp; non-destructive chunked import.
            </div>
        </div>
        <div class="import-chip">
            <i class="bx bx-user"></i>
            <span id="currentClientCountLabel">{{ number_format($existingClientCount) }} existing clients</span>
        </div>
    </div>

    <div class="import-surface">
        <div id="importErrorAlert" class="alert alert-danger py-2 px-3 small mb-3 d-none" role="alert"></div>

        {{-- STAGE 1: UPLOAD & DRY-RUN PREVIEW --}}
        <section id="stageOneSection">
            <div class="import-step-tag">Stage 1 &middot; Dry-Run Preview (0 Database Writes)</div>
            <p class="small mb-3" style="color: #b8a29a;">
                Upload your cleaned CSV (max 10 MB). We inspect every row against live CRM clients without writing a single record so you can verify exact counts before importing.
            </p>

            <form id="dryRunForm" novalidate>
                @csrf
                <label for="csvFileInput" class="import-dropzone d-block mb-3" id="csvDropzone">
                    <i class="bx bx-cloud-upload fs-2 d-block mb-1" style="color: #e5b8a8;"></i>
                    <div class="fw-semibold" id="csvDropzoneTitle" style="color: #f8efe6; font-size: 14px;">
                        Click to choose CSV file or drag &amp; drop here
                    </div>
                    <div class="small mt-1" id="csvDropzoneSub" style="color: #b8a29a;">
                        Accepts .csv or .txt up to 10 MB &middot; Stored privately &amp; auto-deleted after import
                    </div>
                    <input type="file"
                           name="csv_file"
                           id="csvFileInput"
                           accept=".csv,.txt,text/csv,text/plain"
                           class="d-none">
                </label>

                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
                    <div style="min-width: 240px;">
                        <label for="importSourceInput" class="form-label small fw-semibold mb-1" style="color: #b8a29a;">
                            Import Batch Tag
                        </label>
                        <input type="text"
                               id="importSourceInput"
                               name="source"
                               class="form-control form-control-sm import-input-dark"
                               value="{{ $defaultImportSource }}"
                               maxlength="64">
                    </div>

                    <button type="submit" class="import-btn-primary" id="runDryRunBtn" disabled>
                        <i class="bx bx-search-alt"></i>
                        <span>Run Dry-Run Preview</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- DRY-RUN RESULTS & STAGE 2 UNLOCK --}}
        <section id="dryRunResultSection" class="d-none">
            <div class="import-divider"></div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="import-step-tag">Dry-Run Summary &middot; Ready for Review</div>
                    <div class="small" style="color: #b8a29a;" id="dryRunFileMeta"></div>
                </div>
                <button type="button" class="import-btn-danger" id="cancelImportBtn">
                    <i class="bx bx-trash"></i>
                    <span>Cancel &amp; Delete File</span>
                </button>
            </div>

            <table class="import-summary-table mb-3">
                <tbody>
                    <tr>
                        <th>CSV Rows Read</th>
                        <td id="dryRowsRead">0</td>
                    </tr>
                    <tr>
                        <th>Existing Phones to Normalize (E.164)</th>
                        <td id="dryNormalized">0</td>
                    </tr>
                    <tr>
                        <th>Existing Phone Collisions to Merge</th>
                        <td id="dryCollisions">0</td>
                    </tr>
                    <tr>
                        <th>Existing Clients to Update (Non-Destructive)</th>
                        <td id="dryUpdated">0</td>
                    </tr>
                    <tr>
                        <th>Net-New Clients to Create</th>
                        <td id="dryCreated" style="color: #6ee7b7;">0</td>
                    </tr>
                    <tr>
                        <th>Skipped / Invalid (Unchanged, Invalid Phone, or CSV Duplicates)</th>
                        <td id="drySkipped">0</td>
                    </tr>
                </tbody>
            </table>

            <div id="dryCollisionsDetailWrap" class="mb-3 d-none">
                <div class="small fw-semibold mb-1" style="color: #fcd34d;">
                    Phone Collisions Detected (Will Merge Safely Without Losing Bookings or LTV):
                </div>
                <div class="small" id="dryCollisionsList" style="color: #dfd0ca;"></div>
            </div>

            <div class="import-divider"></div>

            {{-- STAGE 2: LIVE CHUNKED IMPORT --}}
            <div id="stageTwoControls">
                <div class="import-step-tag">Stage 2 &middot; Live Chunked Import ({{ $chunkSize }} Rows / Request)</div>
                <p class="small mb-3" style="color: #b8a29a;">
                    First normalizes existing client phone numbers to E.164, then imports CSV rows sequentially in {{ $chunkSize }}-row chunks with live progress and automatic file cleanup when finished.
                </p>

                <label class="d-flex align-items-start gap-2 mb-3 small" style="cursor: pointer; color: #f8efe6;">
                    <input type="checkbox" id="confirmLiveImportCb" class="mt-1" style="accent-color: #d98f83;">
                    <span>I have reviewed the Dry-Run preview counts above and confirm running the live import.</span>
                </label>

                <button type="button" class="import-btn-primary" id="startLiveImportBtn" disabled>
                    <i class="bx bx-play-circle"></i>
                    <span>Start Live Import</span>
                </button>
            </div>

            {{-- LIVE PROGRESS TRACKER --}}
            <div id="liveProgressSection" class="d-none mt-3">
                <div class="d-flex justify-content-between align-items-center small mb-2">
                    <span id="liveStatusText" class="fw-semibold" style="color: #f8efe6;">Starting live import...</span>
                    <span id="livePercentText" class="fw-bold" style="color: #e5b8a8;">0%</span>
                </div>
                <div class="import-progress-track mb-3">
                    <div class="import-progress-fill" id="liveProgressBar"></div>
                </div>

                <table class="import-summary-table">
                    <tbody>
                        <tr>
                            <th>Existing Phones Normalized</th>
                            <td id="liveNormalized">0</td>
                        </tr>
                        <tr>
                            <th>Collisions Merged</th>
                            <td id="liveCollisions">0</td>
                        </tr>
                        <tr>
                            <th>Existing Clients Updated</th>
                            <td id="liveUpdated">0</td>
                        </tr>
                        <tr>
                            <th>Net-New Clients Created</th>
                            <td id="liveCreated" style="color: #6ee7b7;">0</td>
                        </tr>
                        <tr>
                            <th>Skipped</th>
                            <td id="liveSkipped">0</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- COMPLETION STATE --}}
            <div id="importCompleteBanner" class="d-none mt-4 pt-3" style="border-top: 1px solid rgba(110, 231, 183, 0.24);">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="fw-bold" style="color: #6ee7b7; font-size: 14.5px;">
                            ✓ Live Client Import Complete
                        </div>
                        <div class="small mt-1" id="importCompleteMeta" style="color: #b8a29a;">
                            Uploaded CSV file has been automatically deleted from the server.
                        </div>
                    </div>
                    <a href="{{ route('customers.index') }}" class="import-btn-primary text-decoration-none">
                        <span>View Clients Directory</span>
                        <i class="bx bx-right-arrow-alt"></i>
                    </a>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    const ROUTES = {
        dryRun: @json(route('customers.import.dry-run')),
        normalize: @json(route('customers.import.normalize')),
        chunk: @json(route('customers.import.chunk')),
        cancel: @json(route('customers.import.cancel')),
    };
    const CHUNK_SIZE = {{ (int) $chunkSize }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('#dryRunForm input[name="_token"]')?.value
        || '';

    let activeToken = null;
    let activeImportSource = @json($defaultImportSource);
    let totalCsvRows = 0;
    let isRunning = false;

    const errorAlert = document.getElementById('importErrorAlert');
    const fileInput = document.getElementById('csvFileInput');
    const dropzone = document.getElementById('csvDropzone');
    const dropzoneTitle = document.getElementById('csvDropzoneTitle');
    const dropzoneSub = document.getElementById('csvDropzoneSub');
    const sourceInput = document.getElementById('importSourceInput');
    const runDryRunBtn = document.getElementById('runDryRunBtn');
    const dryRunForm = document.getElementById('dryRunForm');
    const dryRunResultSection = document.getElementById('dryRunResultSection');
    const dryRunFileMeta = document.getElementById('dryRunFileMeta');
    const cancelImportBtn = document.getElementById('cancelImportBtn');
    const confirmCb = document.getElementById('confirmLiveImportCb');
    const startLiveBtn = document.getElementById('startLiveImportBtn');
    const stageTwoControls = document.getElementById('stageTwoControls');
    const liveProgressSection = document.getElementById('liveProgressSection');
    const liveStatusText = document.getElementById('liveStatusText');
    const livePercentText = document.getElementById('livePercentText');
    const liveProgressBar = document.getElementById('liveProgressBar');
    const completeBanner = document.getElementById('importCompleteBanner');
    const completeMeta = document.getElementById('importCompleteMeta');
    const clientCountLabel = document.getElementById('currentClientCountLabel');

    function fmt(n) {
        return Number(n || 0).toLocaleString();
    }

    function showError(msg) {
        if (!msg) {
            errorAlert.textContent = '';
            errorAlert.classList.add('d-none');
            return;
        }
        errorAlert.textContent = msg;
        errorAlert.classList.remove('d-none');
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function updateSelectedFileLabel(file) {
        if (!file) {
            dropzoneTitle.textContent = 'Click to choose CSV file or drag & drop here';
            dropzoneSub.textContent = 'Accepts .csv or .txt up to 10 MB · Stored privately & auto-deleted after import';
            runDryRunBtn.disabled = true;
            return;
        }
        const kb = (file.size / 1024).toFixed(1);
        dropzoneTitle.textContent = file.name;
        dropzoneSub.textContent = kb + ' KB selected · Ready for Stage 1 Dry-Run Preview';
        runDryRunBtn.disabled = false;
    }

    fileInput.addEventListener('change', function () {
        showError('');
        updateSelectedFileLabel(this.files && this.files[0] ? this.files[0] : null);
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });
    dropzone.addEventListener('drop', function (e) {
        const files = e.dataTransfer?.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            showError('');
            updateSelectedFileLabel(files[0]);
        }
    });

    confirmCb.addEventListener('change', function () {
        startLiveBtn.disabled = !this.checked || !activeToken || isRunning;
    });

    async function postJson(url, payload, method) {
        const response = await fetch(url, {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await response.json().catch(() => null) : null;

        if (response.status === 419) {
            throw new Error('Your session expired (419). Please refresh the page and try again.');
        }
        if (response.status === 403) {
            throw new Error((data && data.message) || 'You do not have permission to run client imports.');
        }
        if (!response.ok || !data || data.success !== true) {
            const firstErr = data && data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(firstErr || (data && data.message) || ('Request failed (HTTP ' + response.status + ').'));
        }

        return data;
    }

    // STAGE 1: Upload + Dry-Run
    dryRunForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (isRunning) return;
        showError('');

        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            showError('Please choose a CSV file first.');
            return;
        }

        // Clean up any previously uploaded abandoned token first
        if (activeToken) {
            postJson(ROUTES.cancel, { token: activeToken }, 'DELETE').catch(() => {});
            activeToken = null;
        }

        isRunning = true;
        runDryRunBtn.disabled = true;
        runDryRunBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> <span>Running Dry-Run...</span>';

        try {
            const fd = new FormData();
            fd.append('csv_file', file);
            fd.append('source', (sourceInput.value || '').trim());
            fd.append('_token', csrfToken);

            const response = await fetch(ROUTES.dryRun, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
            });

            const contentType = response.headers.get('content-type') || '';
            const data = contentType.includes('application/json') ? await response.json().catch(() => null) : null;

            if (response.status === 419) {
                throw new Error('Your session expired (419). Please refresh the page and try again.');
            }
            if (response.status === 403) {
                throw new Error((data && data.message) || 'Only administrators can import client CSV files.');
            }
            if (!response.ok || !data || data.success !== true) {
                const firstErr = data && data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(firstErr || (data && data.message) || ('Dry-Run failed (HTTP ' + response.status + ').'));
            }

            activeToken = data.token;
            activeImportSource = data.import_source || activeImportSource;
            const s = data.summary;
            totalCsvRows = Number(s.total_csv_rows || 0);

            dryRunFileMeta.textContent = data.original_name + ' · ' + data.file_size_kb + ' KB · Tag: ' + activeImportSource;
            document.getElementById('dryRowsRead').textContent = fmt(s.total_csv_rows);
            document.getElementById('dryNormalized').textContent = fmt(s.normalized);
            document.getElementById('dryCollisions').textContent = fmt(s.collisions_merged);
            document.getElementById('dryUpdated').textContent = fmt(s.updated);
            document.getElementById('dryCreated').textContent = fmt(s.created);
            document.getElementById('drySkipped').textContent = fmt(s.skipped)
                + ' (' + fmt(s.skipped_unchanged) + ' unchanged, '
                + fmt(s.skipped_invalid) + ' invalid, '
                + fmt(s.skipped_duplicate_in_csv) + ' CSV dupes)';

            const collWrap = document.getElementById('dryCollisionsDetailWrap');
            const collList = document.getElementById('dryCollisionsList');
            if (Array.isArray(s.collisions) && s.collisions.length > 0) {
                collList.textContent = s.collisions
                    .map(c => c.e164 + ' (keep #' + c.winner_id + ', merge #' + c.merged_duplicate_id + ' [' + c.merged_duplicate_phone + '])')
                    .join(' · ');
                collWrap.classList.remove('d-none');
            } else {
                collList.textContent = '';
                collWrap.classList.add('d-none');
            }

            confirmCb.checked = false;
            startLiveBtn.disabled = true;
            stageTwoControls.classList.remove('d-none');
            liveProgressSection.classList.add('d-none');
            completeBanner.classList.add('d-none');
            cancelImportBtn.classList.remove('d-none');
            dryRunResultSection.classList.remove('d-none');
        } catch (err) {
            showError(err.message || 'Dry-Run failed. Please check the CSV file and try again.');
        } finally {
            isRunning = false;
            runDryRunBtn.disabled = !(fileInput.files && fileInput.files[0]);
            runDryRunBtn.innerHTML = '<i class="bx bx-search-alt"></i> <span>Run Dry-Run Preview</span>';
        }
    });

    // CANCEL & DELETE FILE
    cancelImportBtn.addEventListener('click', async function () {
        if (isRunning) return;
        showError('');
        cancelImportBtn.disabled = true;

        try {
            if (activeToken) {
                await postJson(ROUTES.cancel, { token: activeToken }, 'DELETE');
            }
            activeToken = null;
            totalCsvRows = 0;
            fileInput.value = '';
            updateSelectedFileLabel(null);
            dryRunResultSection.classList.add('d-none');
        } catch (err) {
            showError(err.message || 'Unable to delete temporary file.');
        } finally {
            cancelImportBtn.disabled = false;
        }
    });

    // STAGE 2: SEQUENTIAL CHUNKED LIVE IMPORT
    startLiveBtn.addEventListener('click', async function () {
        if (isRunning || !activeToken || !confirmCb.checked) return;
        showError('');

        isRunning = true;
        startLiveBtn.disabled = true;
        runDryRunBtn.disabled = true;
        cancelImportBtn.disabled = true;
        confirmCb.disabled = true;
        liveProgressSection.classList.remove('d-none');
        completeBanner.classList.add('d-none');

        const totals = {
            normalized: 0,
            collisions: 0,
            updated: 0,
            created: 0,
            skipped: 0,
        };

        function syncLiveCounters() {
            document.getElementById('liveNormalized').textContent = fmt(totals.normalized);
            document.getElementById('liveCollisions').textContent = fmt(totals.collisions);
            document.getElementById('liveUpdated').textContent = fmt(totals.updated);
            document.getElementById('liveCreated').textContent = fmt(totals.created);
            document.getElementById('liveSkipped').textContent = fmt(totals.skipped);
        }

        function setProgress(processed, total, label) {
            const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 100;
            liveStatusText.textContent = label;
            livePercentText.textContent = pct + '%';
            liveProgressBar.style.width = pct + '%';
        }

        syncLiveCounters();

        try {
            // Step 2A: Normalize existing CRM clients once
            setProgress(0, Math.max(1, totalCsvRows), 'Step 1/2: Normalizing existing CRM client phones to E.164...');
            const normData = await postJson(ROUTES.normalize, { token: activeToken }, 'POST');
            totals.normalized += Number(normData.summary?.normalized || 0);
            totals.collisions += Number(normData.summary?.collisions_merged || 0);
            syncLiveCounters();

            // Step 2B: Loop 500-row CSV chunks sequentially until has_more === false
            let offset = 0;
            let hasMore = true;
            let finalFileDeleted = false;
            let finalTotalClients = null;

            while (hasMore) {
                setProgress(
                    offset,
                    Math.max(1, totalCsvRows),
                    'Step 2/2: Processing ' + fmt(offset) + ' / ' + fmt(totalCsvRows) + ' rows...'
                );

                const chunkRes = await postJson(ROUTES.chunk, {
                    token: activeToken,
                    offset: offset,
                    limit: CHUNK_SIZE,
                    source: activeImportSource,
                }, 'POST');

                const c = chunkRes.chunk;
                totals.normalized += Number(c.normalized || 0);
                totals.updated += Number(c.updated || 0);
                totals.created += Number(c.created || 0);
                totals.skipped += Number(c.skipped || 0);
                syncLiveCounters();

                offset = Number(c.next_offset || (offset + CHUNK_SIZE));
                hasMore = Boolean(c.has_more);
                finalFileDeleted = Boolean(chunkRes.file_deleted);
                if (chunkRes.total_clients_now !== null && chunkRes.total_clients_now !== undefined) {
                    finalTotalClients = Number(chunkRes.total_clients_now);
                }

                setProgress(
                    Math.min(offset, totalCsvRows),
                    Math.max(1, totalCsvRows),
                    'Step 2/2: Processed ' + fmt(Math.min(offset, totalCsvRows)) + ' / ' + fmt(totalCsvRows) + ' rows'
                );
            }

            setProgress(totalCsvRows, Math.max(1, totalCsvRows), 'Import complete · All ' + fmt(totalCsvRows) + ' CSV rows processed');
            activeToken = null;
            cancelImportBtn.classList.add('d-none');
            stageTwoControls.classList.add('d-none');

            if (finalTotalClients !== null && clientCountLabel) {
                clientCountLabel.textContent = fmt(finalTotalClients) + ' existing clients';
            }

            completeMeta.textContent = finalFileDeleted
                ? 'Uploaded CSV file was automatically deleted from storage/app/imports/uploads/.'
                : 'Live import completed successfully.';
            completeBanner.classList.remove('d-none');
        } catch (err) {
            showError(err.message || 'Live import interrupted. You can review the error and retry.');
            startLiveBtn.disabled = false;
            cancelImportBtn.disabled = false;
            confirmCb.disabled = false;
        } finally {
            isRunning = false;
            runDryRunBtn.disabled = !(fileInput.files && fileInput.files[0]);
        }
    });
})();
</script>
@endsection
