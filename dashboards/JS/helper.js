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
    window.location.href = '../Backend/logout.php';
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
            fetch('actions/submit_overtime.php', { method: 'POST', body: data })
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

    // ── Dynamic scroll for work-list ────────────────────────────
    initWorkListScroll();
});

/* ── Work-list dynamic scroll ───────────────────────────────── */
function initWorkListScroll() {
    const inner   = document.getElementById('work-list-inner');
    const wrapper = inner?.parentElement;
    if (!inner || !wrapper) return;

    const items = inner.querySelectorAll('.work-item');
    if (items.length === 0) return;

    // ── Apply entry animation styles to each item
    items.forEach((item, i) => {
        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        item.style.opacity    = '1';
        item.style.transform  = 'translateY(0)';
    });

    // ── Update fade-edge classes based on scroll position
    function updateEdges() {
        const scrollTop    = inner.scrollTop;
        const scrollBottom = inner.scrollHeight - inner.clientHeight - scrollTop;

        wrapper.classList.toggle('at-top',    scrollTop    < 4);
        wrapper.classList.toggle('at-bottom', scrollBottom < 4);
        wrapper.classList.toggle('scrolling', scrollTop > 4 && scrollBottom > 4);
    }

    // ── Animate items entering/leaving viewport using IntersectionObserver
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity   = '1';
                entry.target.style.transform = 'translateY(0)';
            } else {
                // Decide which direction the item is exiting
                const rect      = entry.target.getBoundingClientRect();
                const ctxRect   = inner.getBoundingClientRect();
                const exitingUp = rect.top < ctxRect.top;
                entry.target.style.opacity   = '0';
                entry.target.style.transform = exitingUp ? 'translateY(-10px)' : 'translateY(10px)';
            }
        });
    }, {
        root: inner,
        threshold: 0.25,   // item must be 25% visible to be considered "in"
    });

    items.forEach(item => observer.observe(item));

    // Initial edge state
    updateEdges();

    // Update on scroll
    inner.addEventListener('scroll', updateEdges, { passive: true });
}