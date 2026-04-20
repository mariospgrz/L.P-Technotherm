// dashboards/JS/supervisor.js
'use strict';

/* ── CSRF helper ─────────────────────────────────────────────── */
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/* ── State ──────────────────────────────────────────────────── */
const state = {
    activeTab: 'hours',
    projects: window.__PROJECTS__ || [],
    helpers: window.__HELPERS__ || [],
    invoices: window.__INVOICES__ || [],
    workLogs: window.__WORK_LOGS__ || [],
    overtime: window.__OVERTIME__ || [],
    assignments: window.__ASSIGNMENTS__ || [],
    selectedProjectId: null,
    selectedProjectName: null,
    // clockedIn / clockInTime are now owned by clock_timer.js (ClockTimer)
};

/* ── Tab switching ──────────────────────────────────────── */
function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    const btn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
    const panel = document.getElementById(`panel-${tabName}`);
    if (btn) btn.classList.add('active');
    if (panel) panel.classList.add('active');
    state.activeTab = tabName;

    if (tabName === 'invoices-mine') renderMyInvoices();
    if (tabName === 'overtime') renderOvertimeList();
    if (tabName === 'assign') renderAssignments();
}

/* ── Clock In / Out (Καταγραφή Ωρών) ───────────────────── */
// updateStatusCard is now handled by clock_timer.js / ClockTimer
function updateStatusCard() { /* delegated to ClockTimer */ }

function clockToggle() {
    if (ClockTimer.isActive()) {
        // Currently clocked in → clock out
        ClockTimer.clockOut();
    } else {
        // Currently clocked out → clock in
        if (!state.selectedProjectId) {
            // Try to show a toast – re-use ClockTimer's internal toast via a manual call
            const t = document.createElement('div');
            t.id = 'ct-toast';
            t.textContent = 'Επιλέξτε έργο πρώτα!';
            Object.assign(t.style, {
                position: 'fixed', bottom: '80px', right: '24px', padding: '12px 20px',
                borderRadius: '8px', background: 'var(--danger,#ef4444)', color: '#fff',
                fontWeight: '600', fontSize: '0.875rem', zIndex: '9999'
            });
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
            return;
        }
        ClockTimer.clockIn(state.selectedProjectId, state.selectedProjectName);
    }
}

/* ── Project cards ──────────────────────────────────────── */
function selectProject(id) {
    // Don't allow changing project while clocked in
    if (ClockTimer.isActive()) {
        showToast('Αποσυνδεθείτε πρώτα (πατήστε Clock Out)', 'error');
        return;
    }
    state.selectedProjectId = id;
    const proj = state.projects.find(p => String(p.id) === String(id));
    state.selectedProjectName = proj ? proj.name : '';

    document.querySelectorAll('.project-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.project-card[data-id="${id}"]`);
    if (card) card.classList.add('selected');

    // Update clock section
    const nameEl = document.getElementById('selected-project-name');
    const btn = document.getElementById('clock-toggle-btn');
    if (nameEl) nameEl.textContent = card ? card.querySelector('h4').textContent.trim() : '';
    if (btn) btn.disabled = false;
}

/* ── Invoice submit ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {

    // Tab click listeners
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    // Project card selection
    document.querySelectorAll('.project-card').forEach(card => {
        card.addEventListener('click', () => selectProject(card.dataset.id));
    });

    // Invoice form submission
    const invForm = document.getElementById('invoice-form');
    if (invForm) {
        invForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const data = new FormData(this);
            data.append('csrf_token', getCsrf());

            fetch('../Backend/upload_invoice.php', {
                method: 'POST',
                body: data,
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // Add the new invoice to state so the list updates without a page reload
                        if (res.invoice) {
                            state.invoices.unshift({
                                id: res.invoice.id,
                                description: res.invoice.description,
                                project: res.invoice.project,
                                amount: res.invoice.amount,
                                date: res.invoice.date,
                                photo_url: res.invoice.photo_url || null,
                            });
                        }
                        showToast('Τιμολόγιο καταχωρήθηκε!', 'success');
                        invForm.reset();
                        document.getElementById('invoice-photo-label').textContent = 'Επιλέξτε αρχείο';
                        switchTab('invoices-mine');
                    } else {
                        showToast(res.message || 'Σφάλμα καταχώρησης', 'error');
                    }
                })
                .catch(() => showToast('Σφάλμα δικτύου', 'error'));
        });
    }

    // Overtime form
    const otForm = document.getElementById('overtime-form');
    if (otForm) {
        otForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const data = new FormData(this);
            data.append('csrf_token', getCsrf());

            fetch('actions/submit_overtime.php', {
                method: 'POST',
                body: data,
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('Αίτημα υπερωρίας υποβλήθηκε!', 'success');
                        otForm.reset();
                        closeModal('overtime-modal');
                        switchTab('overtime');
                    } else {
                        showToast(res.message || 'Σφάλμα υποβολής', 'error');
                    }
                })
                .catch(() => showToast('Σφάλμα δικτύου', 'error'));
        });
    }

    // Assign helpers form
    const assignForm = document.getElementById('assign-form');
    if (assignForm) {
        assignForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const data = new FormData(this);
            data.append('csrf_token', getCsrf());

            fetch('actions/assign_helpers.php', {
                method: 'POST',
                body: data,
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('Ανάθεση αποθηκεύτηκε!', 'success');

                        const projectId = String(data.get('project_id'));
                        const helperIds = data.getAll('helper_ids[]');
                        const pFound = state.projects.find(p => String(p.id) === projectId);

                        if (pFound) {
                            const pName = pFound.name;
                            const hNames = state.helpers
                                .filter(h => helperIds.includes(String(h.id)))
                                .map(h => h.name);

                            state.assignments = state.assignments.filter(a => a.project !== pName);
                            if (hNames.length > 0) {
                                state.assignments.push({ project: pName, helpers: hNames });
                                state.assignments.sort((a, b) => a.project.localeCompare(b.project));
                            }
                        }

                        renderAssignments();
                    } else {
                        showToast(res.message || 'Σφάλμα ανάθεσης', 'error');
                    }
                })
                .catch(() => showToast('Σφάλμα δικτύου', 'error'));
        });
    }

    // File upload label
    const fileInput = document.getElementById('invoice-photo');
    const fileLabel = document.getElementById('invoice-photo-label');
    if (fileInput && fileLabel) {
        fileInput.addEventListener('change', () => {
            fileLabel.textContent = fileInput.files[0]
                ? fileInput.files[0].name
                : 'Επιλέξτε αρχείο';
        });
    }

    // Assign Helpers Project Selection Change
    const assignProjectSelect = document.getElementById('assign-project');
    const assignOverlay = document.getElementById('helpers-disabled-overlay');
    if (assignProjectSelect) {
        assignProjectSelect.addEventListener('change', function () {
            const projectId = this.value;
            const pFound = state.projects.find(p => String(p.id) === projectId);

            // Uncheck all first
            document.querySelectorAll('.helper-checkbox').forEach(cb => {
                cb.checked = false;
            });

            if (pFound) {
                // Remove disabled overlay
                if (assignOverlay) assignOverlay.style.display = 'none';

                // Find assigned helpers for this project
                const assignment = state.assignments.find(a => a.project === pFound.name);
                if (assignment && assignment.helpers) {
                    // Check the boxes for assigned helpers
                    const helperNames = assignment.helpers;
                    state.helpers.forEach(h => {
                        if (helperNames.includes(h.name)) {
                            const cb = document.getElementById(`h-${h.id}`);
                            if (cb) cb.checked = true;
                        }
                    });
                }
            } else {
                if (assignOverlay) assignOverlay.style.display = 'flex';
            }
            // Update counter UI
            updateSelectedCount();
        });

        // Initial state
        if (!assignProjectSelect.value && assignOverlay) {
            assignOverlay.style.display = 'flex';
        }
    }

    // Initial render
    renderMyInvoices();
    renderOvertimeList();
    renderAssignments();
    // NOTE: updateStatusCard() / clock state restoration is handled by clock_timer.js

    // ── Dynamic scroll for work-list ────────────────────────────
    initWorkListScroll('sup-work-list-hours');
    initWorkListScroll('sup-work-list-mine');
    initWorkListScroll('sup-work-list-overtime');
});

/* ── Work-list dynamic scroll ───────────────────────────────── */
function initWorkListScroll(containerId) {
    const inner = document.getElementById(containerId);
    const wrapper = inner?.parentElement;
    if (!inner || !wrapper) return;

    const items = inner.querySelectorAll('.work-item');
    if (items.length === 0) return;

    // Apply entry animation styles to each item
    items.forEach((item) => {
        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        item.style.opacity = '1';
        item.style.transform = 'translateY(0)';
    });

    // Update fade-edge classes based on scroll position
    function updateEdges() {
        const scrollTop = inner.scrollTop;
        const scrollBottom = inner.scrollHeight - inner.clientHeight - scrollTop;

        wrapper.classList.toggle('at-top', scrollTop < 4);
        wrapper.classList.toggle('at-bottom', scrollBottom < 4);
        wrapper.classList.toggle('scrolling', scrollTop > 4 && scrollBottom > 4);
    }

    // Animate items entering/leaving viewport using IntersectionObserver
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            } else {
                const rect = entry.target.getBoundingClientRect();
                const ctxRect = inner.getBoundingClientRect();
                const exitingUp = rect.top < ctxRect.top;
                entry.target.style.opacity = '0';
                entry.target.style.transform = exitingUp ? 'translateY(-10px)' : 'translateY(10px)';
            }
        });
    }, {
        root: inner,
        threshold: 0.25,
    });

    items.forEach(item => observer.observe(item));

    updateEdges();
    inner.addEventListener('scroll', updateEdges, { passive: true });
}

/* ── Render My Invoices ─────────────────────────────────── */
function renderMyInvoices(query = '') {
    const container = document.getElementById('my-invoices-list');
    if (!container) return;

    let list = state.invoices;
    if (query) {
        const q = query.toLowerCase();
        list = list.filter(inv =>
            inv.description.toLowerCase().includes(q) ||
            inv.project.toLowerCase().includes(q)
        );
    }

    if (list.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>Δεν υπάρχουν τιμολόγια ακόμα</p>
            </div>`;
        return;
    }

    container.innerHTML = list.map(inv => {
        const photoUrl = inv.photo_url || '';
        const isImage = photoUrl && /\.(jpe?g|png|webp|gif)$/i.test(photoUrl);
        const isPdf = photoUrl && /\.pdf$/i.test(photoUrl);
        const finalPhotoUrl = photoUrl.startsWith('http') ? photoUrl : '/' + photoUrl;

        const thumbHtml = isImage
            ? `<img src="${finalPhotoUrl}" class="inv-thumb" onclick="supOpenImage('${finalPhotoUrl}')" title="Προβολή">`
            : `<div class="invoice-icon-box"><i class="fas fa-file-alt"></i></div>`;

        const viewBtn = photoUrl
            ? `<button class="btn-inv-view-sup" onclick="supOpenImage('${finalPhotoUrl}')" title="Εικόνα"><i class="fas fa-eye"></i></button>`
            : '';

        const supplierEsc = (inv.description || '').replace(/'/g, "\\'");

        return `
        <div class="invoice-item" id="inv-${inv.id}">
            ${thumbHtml}
            <div class="invoice-info">
                <strong>${esc(inv.description)}</strong>
                <small><i class="fas fa-building"></i> ${esc(inv.project)}</small>
            </div>
            <div class="invoice-amount">
                <strong>€${fmt(inv.amount)}</strong>
                <small>${inv.date}</small>
            </div>
            <div class="invoice-actions">
                ${viewBtn}
                <button class="icon-btn" title="Επεξεργασία" onclick="editInvoice(${inv.id}, '${supplierEsc}', ${inv.amount})">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="icon-btn danger" title="Διαγραφή" onclick="deleteInvoice(${inv.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`;
    }).join('');
}

function filterInvoices() {
    const q = document.getElementById('invoice-search').value;
    renderMyInvoices(q);
}

async function deleteInvoice(id) {
    const result = await Swal.fire({
        title: 'Είστε σίγουροι;',
        text: 'Το τιμολόγιο θα διαγραφεί οριστικά.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Ναι, Διαγραφή',
        cancelButtonText: 'Ακύρωση'
    });
    if (!result.isConfirmed) return;
    fetch('actions/delete_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf_token: getCsrf() }),
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                state.invoices = state.invoices.filter(i => i.id !== id);
                renderMyInvoices();
                showToast('Τιμολόγιο διαγράφηκε', 'success');
            } else {
                showToast(res.message || 'Σφάλμα', 'error');
            }
        });
}

function editInvoice(id, supplier, amount) {
    document.getElementById('supEditInvId').value = id;
    document.getElementById('supEditInvSupplier').value = supplier;
    document.getElementById('supEditInvAmount').value = amount;
    const msgEl = document.getElementById('sup-edit-msg');
    if (msgEl) { msgEl.style.display = 'none'; msgEl.textContent = ''; }
    document.getElementById('supEditInvModal').classList.add('show');
}

function supCloseEditModal() {
    document.getElementById('supEditInvModal').classList.remove('show');
}

async function supSubmitEditInvoice(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('supEditInvId').value);
    const supplier = document.getElementById('supEditInvSupplier').value.trim();
    const amount = parseFloat(document.getElementById('supEditInvAmount').value);
    const btn = document.getElementById('supEditInvBtn');
    const msgEl = document.getElementById('sup-edit-msg');

    if (!supplier || !amount || amount <= 0) {
        msgEl.textContent = 'Συμπληρώστε προμηθευτή και έγκυρο ποσό.';
        msgEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;font-size:0.82rem;font-weight:500;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-bottom:12px;';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Αποθήκευση…';

    try {
        const res2 = await fetch('actions/supervisor_edit_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, vendor: supplier, amount, csrf_token: getCsrf() })
        });
        const data = await res2.json();
        if (data.success) {
            // Update local state
            const inv = state.invoices.find(i => i.id === id);
            if (inv) { inv.description = supplier; inv.amount = amount; }
            supCloseEditModal();
            renderMyInvoices();
            showToast('Τιμολόγιο ενημερώθηκε!', 'success');
        } else {
            msgEl.textContent = data.message || 'Σφάλμα ενημέρωσης.';
            msgEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;font-size:0.82rem;font-weight:500;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-bottom:12px;';
        }
    } catch {
        msgEl.textContent = 'Σφάλμα σύνδεσης.';
        msgEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;font-size:0.82rem;font-weight:500;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-bottom:12px;';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Αποθήκευση';
    }
}

/* ── Image Viewer ────────────────────────────────────────── */
function supOpenImage(url) {
    if (/\.pdf$/i.test(url)) { window.open(url, '_blank'); return; }
    document.getElementById('supImgViewerImg').src = url;
    document.getElementById('supImgViewer').classList.add('show');
}

function supCloseImage(event) {
    if (event && event.target !== document.getElementById('supImgViewer')) return;
    document.getElementById('supImgViewer').classList.remove('show');
    document.getElementById('supImgViewerImg').src = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('supImgViewer')?.classList.remove('show');
        document.getElementById('supEditInvModal')?.classList.remove('show');
    }
});

/* ── Render Overtime list ───────────────────────────────── */
function renderOvertimeList() {
    const container = document.getElementById('overtime-list');
    if (!container) return;

    if (state.overtime.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>Δεν έχετε υποβάλει αιτήματα υπερωριών ακόμα</p>
            </div>`;
        return;
    }

    container.innerHTML = state.overtime.map(ot => `
        <div class="overtime-item">
            <div class="ot-left">
                <strong>${esc(ot.project)}</strong>
                <small>${ot.date} · ${ot.hours} ώρες</small>
               <br> <small><strong>Λόγος:</strong> ${esc(ot.reason)}</small>
            </div>
            <span class="badge ${ot.status}">${statusLabel(ot.status)}</span>
        </div>`).join('');
}

/* ── Render Assignments ─────────────────────────────────── */
function renderAssignments() {
    const container = document.getElementById('current-assignments');
    if (!container) return;

    if (state.assignments.length === 0) {
        container.innerHTML = `<p style="color:var(--text-muted);font-size:0.875rem;">Δεν υπάρχουν τρέχουσες αναθέσεις.</p>`;
        return;
    }

    container.innerHTML = state.assignments.map(a => `
        <div class="assignment-item">
            <h5>${esc(a.project)}</h5>
            <div class="helpers-chips">
                ${a.helpers.map(h => `<span class="helper-chip">${esc(h)}</span>`).join('')}
            </div>
        </div>`).join('');
}

/* ── Modals ─────────────────────────────────────────────── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close on backdrop click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }

    // Deselect project when clicking outside project cards – but NOT while clocked in
    if (!e.target.closest('.project-card') && !ClockTimer.isActive()) {
        state.selectedProjectId = null;
        state.selectedProjectName = null;
        document.querySelectorAll('.project-card').forEach(c => c.classList.remove('selected'));
        const nameEl = document.getElementById('selected-project-name');
        const btn = document.getElementById('clock-toggle-btn');
        if (nameEl) nameEl.textContent = 'Επιλέξτε ένα έργο για να ξεκινήσετε την καταγραφή';
        if (btn) btn.disabled = true;
    }

    // Close helper dropdown if clicked outside
    const dropdown = document.getElementById('helpers-dropdown');
    if (dropdown && !dropdown.contains(e.target)) {
        const header = document.querySelector('.dropdown-header');
        const body = document.getElementById('helpers-dropdown-body');
        if (header && body) {
            header.classList.remove('active');
            body.classList.remove('show');
        }
    }
});

/* ── Logout ─────────────────────────────────────────────── */
function handleLogout() {
    window.location.href = '../Backend/logout.php';
}

/* ── Toast notification ─────────────────────────────────── */
function showToast(msg, type = 'success') {
    const existing = document.getElementById('toast');
    if (existing) existing.remove();

    const t = document.createElement('div');
    t.id = 'toast';
    t.textContent = msg;
    Object.assign(t.style, {
        position: 'fixed',
        bottom: '80px',
        right: '24px',
        padding: '12px 20px',
        borderRadius: '8px',
        background: type === 'success' ? 'var(--success)' : 'var(--danger)',
        color: '#fff',
        fontWeight: '600',
        fontSize: '0.875rem',
        zIndex: '9999',
        boxShadow: '0 4px 12px rgba(0,0,0,.15)',
        animation: 'fadeIn .25s ease',
    });
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function updateSelectedCount() {
    const checkedBoxes = document.querySelectorAll('.helper-checkbox:checked');
    const label = document.getElementById('helpers-count-label');
    const title = document.getElementById('dropdown-title');
    const count = checkedBoxes.length;

    if (label) {
        label.textContent = `(${count} επιλεγμένοι)`;
    }

    if (title) {
        if (count === 0) {
            title.textContent = 'Επιλέξτε Βοηθούς...';
        } else if (count === 1) {
            const firstCheckedId = checkedBoxes[0].id.replace('h-', '');
            const hFound = window.__HELPERS__?.find(h => String(h.id) === String(firstCheckedId));
            title.textContent = hFound ? hFound.name : '1 βοηθός';
        } else {
            title.textContent = `${count} βοηθοί επιλέχθηκαν`;
        }
    }

    // Update individual helper badges
    document.querySelectorAll('.helper-checkbox').forEach(cb => {
        const badgeId = cb.id.replace('h-', 'h-badge-');
        const badge = document.getElementById(badgeId);
        if (badge) {
            badge.textContent = cb.checked ? 'Αφαίρεση' : 'Επίλεξε';
        }
    });
}

function toggleHelperDropdown(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const header = document.querySelector('.dropdown-header');
    const body = document.getElementById('helpers-dropdown-body');
    if (header && body) {
        header.classList.toggle('active');
        body.classList.toggle('show');
    }
}

function filterHelpers() {
    const query = document.getElementById('helper-search').value.toLowerCase();
    const cards = document.querySelectorAll('.helpers-multi-select .helper-card');
    cards.forEach(card => {
        const name = card.getAttribute('data-name') || '';
        if (name.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

/* ── Helpers ────────────────────────────────────────────── */
function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fmt(n) {
    return Number(n).toLocaleString('el-GR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusLabel(s) {
    return { pending: 'Σε αναμονή', approved: 'Εγκρίθηκε', rejected: 'Απορρίφθηκε' }[s] || s;
}
