// dashboards/JS/supervisor.js
'use strict';

/* ── State ──────────────────────────────────────────────── */
const state = {
    activeTab: 'hours',
    projects: window.__PROJECTS__ || [],
    helpers: window.__HELPERS__ || [],
    invoices: window.__INVOICES__ || [],
    workLogs: window.__WORK_LOGS__ || [],
    overtime: window.__OVERTIME__ || [],
    assignments: window.__ASSIGNMENTS__ || [],
    selectedProjectId: null,
    clockedIn: false,
    clockInTime: null,
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
function updateStatusCard() {
    const el = document.getElementById('status-value');
    if (!el) return;
    if (state.clockedIn) {
        el.textContent = 'Εντός Εργασίας';
        el.parentElement.closest('.status-card').style.borderLeft = '4px solid var(--success)';
        document.getElementById('clock-toggle-btn').textContent = '⏹ Clock Out';
        document.getElementById('clock-toggle-btn').classList.remove('btn-success');
        document.getElementById('clock-toggle-btn').classList.add('btn-danger');
    } else {
        el.textContent = 'Εκτός Εργασίας';
        el.parentElement.closest('.status-card').style.borderLeft = '4px solid var(--border-color)';
        document.getElementById('clock-toggle-btn').textContent = '▶ Clock In';
        document.getElementById('clock-toggle-btn').classList.add('btn-success');
        document.getElementById('clock-toggle-btn').classList.remove('btn-danger');
    }
}

function clockToggle() {
    if (!state.selectedProjectId) {
        showToast('Επιλέξτε έργο πρώτα!', 'error');
        return;
    }
    if (!state.clockedIn) {
        state.clockedIn = true;
        state.clockInTime = new Date();
        showToast('Clock In καταχωρήθηκε!', 'success');
    } else {
        const mins = Math.round((new Date() - state.clockInTime) / 60000);
        state.clockedIn = false;
        state.clockInTime = null;
        showToast(`Clock Out! Εργάστηκες ${mins} λεπτά.`, 'success');
    }
    updateStatusCard();
}

/* ── Project cards ──────────────────────────────────────── */
function selectProject(id) {
    state.selectedProjectId = id;
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

            fetch('/dashboards/actions/submit_invoice.php', {
                method: 'POST',
                body: data,
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('Τιμολόγιο καταχωρήθηκε!', 'success');
                        invForm.reset();
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

            fetch('/dashboards/actions/submit_overtime.php', {
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

            fetch('/dashboards/actions/assign_helpers.php', {
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
    updateStatusCard();
});

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

    container.innerHTML = list.map(inv => `
        <div class="invoice-item" id="inv-${inv.id}">
            <div class="invoice-icon-box"><i class="fas fa-file-alt"></i></div>
            <div class="invoice-info">
                <strong>${esc(inv.description)}</strong>
                <small><i class="fas fa-building"></i> ${esc(inv.project)}</small>
            </div>
            <div class="invoice-amount">
                <strong>€${fmt(inv.amount)}</strong>
                <small>${inv.date}</small>
            </div>
            <div class="invoice-actions">
                <button class="icon-btn" title="Επεξεργασία" onclick="editInvoice(${inv.id})">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="icon-btn danger" title="Διαγραφή" onclick="deleteInvoice(${inv.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>`).join('');
}

function filterInvoices() {
    const q = document.getElementById('invoice-search').value;
    renderMyInvoices(q);
}

function deleteInvoice(id) {
    if (!confirm('Διαγραφή τιμολογίου;')) return;
    fetch('/dashboards/actions/delete_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
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

function editInvoice(id) {
    // Open edit modal (simplified: redirect for now)
    window.location.href = `/dashboards/actions/edit_invoice.php?id=${id}`;
}

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

    // Deselect project when clicking outside project cards
    if (!e.target.closest('.project-card')) {
        state.selectedProjectId = null;
        document.querySelectorAll('.project-card').forEach(c => c.classList.remove('selected'));
        const nameEl = document.getElementById('selected-project-name');
        const btn = document.getElementById('clock-toggle-btn');
        if (nameEl) nameEl.textContent = 'Επιλέξτε ένα έργο για να ξεκινήσετε την καταγραφή';
        if (btn) btn.disabled = true;
    }
});

/* ── Logout ─────────────────────────────────────────────── */
function handleLogout() {
    window.location.href = '/Backend/logout.php';
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
    if (label) {
        label.textContent = `(${checkedBoxes.length} επιλεγμένοι)`;
    }
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
