// dashboards/JS/helper.js
'use strict';

/* ── CSRF helper ────────────────────────────────────────────── */
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/* ── State ──────────────────────────────────────────────────── */
const state = {
    projects: window.__PROJECTS__ || [],
    workLogs: window.__WORK_LOGS__ || [],
    overtime: window.__OVERTIME__ || [],
    selectedProjectId: null,
    selectedProjectName: null,
    // clockedIn state is now owned by clock_timer.js (ClockTimer)
};

/* ── Project selection ──────────────────────────────────────── */
function selectProject(id) {
    // Don't allow switching project while clocked in
    if (ClockTimer.isActive()) {
        showToast('Κάντε Clock Out πρώτα για να αλλάξετε έργο!', 'error');
        return;
    }
    state.selectedProjectId = id;
    const proj = state.projects.find(p => String(p.id) === String(id));
    state.selectedProjectName = proj ? proj.name : '';

    document.querySelectorAll('.project-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`.project-card[data-id="${id}"]`);
    if (card) card.classList.add('selected');

    const nameEl = document.getElementById('selected-project-name');
    const btn    = document.getElementById('clock-toggle-btn');
    if (nameEl) nameEl.textContent = card ? card.querySelector('h4').textContent.trim() : '';
    if (btn)    btn.disabled = false;

    // Scroll to clock section smoothly
    document.getElementById('clock-section')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ── Clock toggle — delegates to ClockTimer ─────────────────── */
function clockToggle() {
    if (ClockTimer.isActive()) {
        // Currently clocked in → clock out
        ClockTimer.clockOut();
    } else {
        // Currently clocked out → clock in
        if (!state.selectedProjectId) {
            showToast('Επιλέξτε έργο πρώτα!', 'error');
            return;
        }
        ClockTimer.clockIn(state.selectedProjectId, state.selectedProjectName);
    }
}

/* ── Modals ─────────────────────────────────────────────────── */
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

/* ── Logout ─────────────────────────────────────────────────── */
function handleLogout() {
    window.location.href = '/Backend/logout.php';
}

/* ── Toast ──────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const existing = document.getElementById('toast');
    if (existing) existing.remove();

    const t = document.createElement('div');
    t.id = 'toast';
    t.textContent = msg;
    Object.assign(t.style, {
        position:     'fixed',
        bottom:       '80px',
        right:        '24px',
        padding:      '12px 20px',
        borderRadius: '8px',
        background:   type === 'success' ? 'var(--success)' : 'var(--danger)',
        color:        '#fff',
        fontWeight:   '600',
        fontSize:     '0.875rem',
        zIndex:       '9999',
        boxShadow:    '0 4px 12px rgba(0,0,0,.15)',
        animation:    'fadeIn .25s ease',
    });
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

/* ── DOMContentLoaded ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {

    // Close modal on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Auto-select if only one project assigned
    const cards = document.querySelectorAll('.project-card');
    if (cards.length === 1) {
        selectProject(cards[0].dataset.id);
    }

    // Overtime form – real fetch POST
    const otForm = document.getElementById('overtime-form');
    if (otForm) {
        otForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const data = new FormData(this);
            data.append('csrf_token', getCsrf());
            fetch('/dashboards/actions/submit_overtime.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        showToast('Αίτημα υπερωρίας υποβλήθηκε!', 'success');
                        otForm.reset();
                        closeModal('overtime-modal');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(res.message || 'Σφάλμα υποβολής', 'error');
                    }
                })
                .catch(() => showToast('Σφάλμα δικτύου', 'error'));
        });
    }
});