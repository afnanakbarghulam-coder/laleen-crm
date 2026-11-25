{{-- Admin-Only Private Staff Documents Modal (opened from Directory & Profiles -> Actions -> Documents) --}}
@if ($isAdminViewer ?? false)
<div class="modal fade sa-modal" id="staffDocumentsModal" tabindex="-1" aria-labelledby="staffDocsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="staffDocsModalTitle" style="color:#f8efe6;">Private Staff Documents</h5>
                    <div class="small" id="staffDocsSubtitle" style="color:#b8a29a;">Stored securely in private HR storage — accessible only to Admin and the employee.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sa-modal-err d-none" id="staffDocsError"></div>

                {{-- Upload Form --}}
                <form id="staffDocUploadForm" enctype="multipart/form-data" class="mb-4 pb-3" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                    @csrf
                    <input type="hidden" id="staffDocStaffId" value="">
                    <div class="sa-modal-section-title">Upload New Document</div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label" for="sdCategory">Category *</label>
                            <select id="sdCategory" name="category" class="form-select" required>
                                @foreach (\App\Models\StaffDocument::CATEGORIES as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="sdTitle">Document Title *</label>
                            <input type="text" id="sdTitle" name="title" class="form-control" required maxlength="255" placeholder="e.g., Qatar ID 2026 or Signed Contract">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sdNumber">Document #</label>
                            <input type="text" id="sdNumber" name="document_number" class="form-control" maxlength="120" placeholder="Optional">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sdIssue">Issue Date</label>
                            <input type="date" id="sdIssue" name="issue_date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sdExpiry">Expiry Date</label>
                            <input type="date" id="sdExpiry" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sdFile">Private File * (PDF / Image, max 10 MB)</label>
                            <input type="file" id="sdFile" name="file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" id="sdUploadBtn" class="sc-btn-rosegold w-100 justify-content-center">
                                <i class="bx bx-upload"></i> Upload
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Documents List --}}
                <div class="sa-modal-section-title">Stored Documents</div>
                <div id="staffDocsListWrap">
                    <div class="small" style="color:#9e8780;">Loading documents…</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const csrfToken = '{{ csrf_token() }}';

        function escHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        function expiryBadgeHtml(exp) {
            if (!exp || exp.key === 'none') {
                return '<span class="badge" style="background:rgba(255,255,255,0.06); color:#b8a29a;">No Expiry</span>';
            }
            if (exp.key === 'expired') {
                return '<span class="badge" style="background:rgba(253,164,175,0.16); color:#fda4af; border:1px solid rgba(253,164,175,0.34);">Expired</span>';
            }
            if (exp.key === 'expiring_soon') {
                return `<span class="badge" style="background:rgba(245,158,11,0.16); color:#fbbf24; border:1px solid rgba(245,158,11,0.34);">Expiring Soon (${exp.days_remaining}d)</span>`;
            }
            return '<span class="badge" style="background:rgba(110,231,183,0.14); color:#6ee7b7; border:1px solid rgba(110,231,183,0.3);">Valid</span>';
        }

        function renderDocs(docs) {
            const wrap = document.getElementById('staffDocsListWrap');
            if (!wrap) return;
            if (!Array.isArray(docs) || docs.length === 0) {
                wrap.innerHTML = '<div class="small py-3 text-center" style="color:#9e8780;">No private documents uploaded for this employee yet.</div>';
                return;
            }

            wrap.innerHTML = docs.map(d => `
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-2" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                    <div class="min-w-0">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="badge" style="background:rgba(212,163,115,0.16); color:#f3d9a4;">${escHtml(d.category_label)}</span>
                            <span class="fw-semibold" style="color:#f8efe6; font-size:13.5px;">${escHtml(d.title)}</span>
                            ${expiryBadgeHtml(d.expiry_status)}
                        </div>
                        <div class="small mt-1" style="color:#9e8780;">
                            ${d.document_number ? `Ref: ${escHtml(d.document_number)} · ` : ''}
                            ${d.issue_date_label ? `Issued: ${escHtml(d.issue_date_label)} · ` : ''}
                            ${d.expiry_date_label ? `Expires: ${escHtml(d.expiry_date_label)} · ` : ''}
                            ${escHtml(d.original_filename)} (${escHtml(d.file_size_label)})
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="${escHtml(d.inline_url)}" target="_blank" class="portal-mini-btn text-decoration-none">
                            <i class="bx bx-show"></i> View
                        </a>
                        <a href="${escHtml(d.download_url)}" class="portal-mini-btn text-decoration-none">
                            <i class="bx bx-download"></i> Download
                        </a>
                        <button type="button" class="portal-mini-btn" style="border-color:rgba(253,164,175,0.34); color:#fda4af;" onclick="deleteStaffDocument('${escHtml(d.delete_url)}')">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        async function loadStaffDocuments(staffId) {
            const errBox = document.getElementById('staffDocsError');
            errBox?.classList.add('d-none');
            const wrap = document.getElementById('staffDocsListWrap');
            if (wrap) wrap.innerHTML = '<div class="small" style="color:#9e8780;">Loading documents…</div>';

            try {
                const res = await fetch(`/staffs/${staffId}/documents`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (res.ok && data.success) {
                    renderDocs(data.documents || []);
                } else if (errBox) {
                    errBox.textContent = data.message || 'Could not load documents.';
                    errBox.classList.remove('d-none');
                }
            } catch (e) {
                if (errBox) {
                    errBox.textContent = 'Network error loading documents.';
                    errBox.classList.remove('d-none');
                }
            }
        }

        window.openStaffDocumentsModal = function (staffId, staffName, locationLabel) {
            document.getElementById('staffDocStaffId').value = String(staffId);
            document.getElementById('staffDocsModalTitle').textContent = `Documents — ${staffName}`;
            document.getElementById('staffDocsSubtitle').textContent = `${locationLabel || 'Staff'} · Private encrypted-disk storage (Admin & employee only).`;
            document.getElementById('staffDocUploadForm')?.reset();
            const modalEl = document.getElementById('staffDocumentsModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            loadStaffDocuments(staffId);
        };

        window.deleteStaffDocument = async function (deleteUrl) {
            if (!confirm('Permanently delete this private document and its stored file?')) return;
            const staffId = document.getElementById('staffDocStaffId').value;
            try {
                const res = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (typeof showStaffToast === 'function') showStaffToast(data.message);
                    loadStaffDocuments(staffId);
                } else {
                    alert(data.message || 'Failed to delete document.');
                }
            } catch (e) {
                alert('Network error deleting document.');
            }
        };

        document.getElementById('staffDocUploadForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const staffId = document.getElementById('staffDocStaffId').value;
            const errBox = document.getElementById('staffDocsError');
            const btn = document.getElementById('sdUploadBtn');
            errBox?.classList.add('d-none');
            btn.disabled = true;
            btn.innerHTML = 'Uploading…';

            try {
                const formData = new FormData(this);
                const res = await fetch(`/staffs/${staffId}/documents`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    this.reset();
                    if (typeof showStaffToast === 'function') showStaffToast(data.message);
                    loadStaffDocuments(staffId);
                } else {
                    const msg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Upload failed.');
                    if (errBox) { errBox.textContent = msg; errBox.classList.remove('d-none'); }
                }
            } catch (err) {
                if (errBox) { errBox.textContent = 'Network error uploading file.'; errBox.classList.remove('d-none'); }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-upload"></i> Upload';
            }
        });
    })();
</script>
@endif
