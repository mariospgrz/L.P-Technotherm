// dashboards/JS/clock_timer.js
// ============================================================
// Shared Clock In / Clock Out timer module.
// Used by both supervisor and helper dashboards.
//
// Key design decisions:
//  - On DOMContentLoaded: calls clock_status.php to restore timer
//    state after page reload or logout (server is source of truth).
//  - Provides ClockTimer.clockIn(projectId, projectName)
//  - Provides ClockTimer.clockOut()
//  - Drives a live HH:MM:SS counter in #clock-timer-display
//  - Polls server every 60s to detect auto-clockout by MySQL event
//  - Shows a warning banner at 7h 30m
//  - Does NOT auto-reload at 8h (avoids infinite loop when MySQL
//    event hasn't fired yet — the 60s poll handles it gracefully)
// ============================================================
'use strict';

const ClockTimer = (() => {

    // ── Internal state ────────────────────────────────────────────────────────
    let _clockInTime   = null;   // JS Date parsed from server clock_in timestamp
    let _projectId     = null;
    let _projectName   = null;
    let _timerInterval = null;   // 1-second display tick
    let _pollInterval  = null;   // 60-second server sync
    let _warned8h      = false;

    // ── Utility ───────────────────────────────────────────────────────────────
    function pad(n) { return String(n).padStart(2, '0'); }

    function formatElapsed(sec) {
        return `${pad(Math.floor(sec / 3600))}:${pad(Math.floor((sec % 3600) / 60))}:${pad(sec % 60)}`;
    }

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // ── DOM accessors ─────────────────────────────────────────────────────────
    const $d = () => document.getElementById('clock-timer-display');
    const $s = () => document.getElementById('status-value');
    const $b = () => document.getElementById('clock-toggle-btn');
    const $p = () => document.getElementById('selected-project-name');
    const $w = () => document.getElementById('clock-warning-banner');

    // ── Timer tick (runs every second) ────────────────────────────────────────
    function _tick() {
        if (!_clockInTime) return;
        const elapsed = Math.floor((Date.now() - _clockInTime.getTime()) / 1000);
        const disp = $d();
        if (disp) {
            disp.textContent = formatElapsed(elapsed);
            disp.classList.add('running');
        }
        // Warning at 7h 30m (27000s)
        if (elapsed >= 27000 && !_warned8h) {
            const w = $w();
            if (w) w.style.display = 'flex';
            _warned8h = true;
        }
        // NO auto-reload at 8h — the 60s poll detects when the MySQL
        // event has clocked the user out and handles it cleanly.
    }

    // ── Server poll (every 60s) ───────────────────────────────────────────────
    // Detects when the MySQL auto-clockout event fires server-side.
    function _pollStatus() {
        fetch('../Backend/ClockInOut/clock_status.php')
            .then(r => r.json())
            .then(data => {
                if (_clockInTime !== null && !data.clocked_in) {
                    // Server says no longer clocked in → MySQL event fired
                    _stopTimer();
                    _setUIClocked(false, null);
                    _showToast('Αυτόματη αποσύνδεση: συμπληρώθηκαν 8 ώρες εργασίας.', 'error');
                    setTimeout(() => window.location.reload(), 2500);
                }
            })
            .catch(() => { /* ignore transient network errors */ });
    }

    // ── Start the live timer ─────────────────────────────────────────────────
    function _startTimer(clockInTimeStr) {
        // Parse MySQL "YYYY-MM-DD HH:MM:SS" as local time
        _clockInTime = new Date(clockInTimeStr.replace(' ', 'T'));
        if (isNaN(_clockInTime.getTime())) {
            console.warn('[ClockTimer] Cannot parse clock_in_time:', clockInTimeStr);
            _clockInTime = null;
            return;
        }

        if (_timerInterval) clearInterval(_timerInterval);
        if (_pollInterval)  clearInterval(_pollInterval);

        _tick();  // render immediately (no 1s wait for first frame)
        _timerInterval = setInterval(_tick, 1000);
        _pollInterval  = setInterval(_pollStatus, 30000);  // poll every 30s
    }

    // ── Stop the live timer ──────────────────────────────────────────────────
    function _stopTimer() {
        if (_timerInterval) { clearInterval(_timerInterval); _timerInterval = null; }
        if (_pollInterval)  { clearInterval(_pollInterval);  _pollInterval  = null; }
        _clockInTime = null;
        _warned8h = false;

        const disp = $d();
        const warn = $w();
        if (disp) { disp.textContent = '00:00:00'; disp.classList.remove('running'); }
        if (warn) warn.style.display = 'none';
    }

    // ── Update button / status card UI ───────────────────────────────────────
    function _setUIClocked(clockedIn, projName) {
        const statusEl = $s();
        const btn      = $b();
        const nameEl   = $p();

        if (clockedIn) {
            if (statusEl) {
                statusEl.textContent = 'Εντός Εργασίας';
                const card = statusEl.closest('.status-card') ||
                             statusEl.parentElement?.closest('.status-card');
                if (card) card.style.borderLeft = '4px solid var(--success)';
            }
            if (btn) {
                btn.innerHTML = '<i class="fas fa-stop"></i> Clock Out - Λήξη Εργασίας';
                btn.className = 'btn-clock-in btn-clock-out';
                btn.disabled  = false;
            }
            if (nameEl && projName) nameEl.textContent = projName;

        } else {
            if (statusEl) {
                statusEl.textContent = 'Εκτός Εργασίας';
                const card = statusEl.closest('.status-card') ||
                             statusEl.parentElement?.closest('.status-card');
                if (card) card.style.borderLeft = '4px solid var(--border-color)';
            }
            if (btn) {
                btn.innerHTML = '<i class="fas fa-play"></i> Clock In - Έναρξη Εργασίας';
                btn.className = 'btn-clock-in';
            }
        }
    }

    // ── Restore state from server on page load ────────────────────────────────
    function _restoreFromServer() {
        fetch('../Backend/ClockInOut/clock_status.php')
            .then(r => r.json())
            .then(data => {
                if (data.clocked_in) {
                    _projectId   = data.project_id;
                    _projectName = data.project_name;

                    // Highlight the active project card
                    document.querySelectorAll('.project-card').forEach(c => {
                        c.classList.toggle('selected',
                            String(c.dataset.id) === String(_projectId));
                    });

                    _setUIClocked(true, data.project_name);
                    _startTimer(data.clock_in_time);
                }
            })
            .catch(err => console.warn('[ClockTimer] Restore failed:', err));
    }

    // ── Public: clockIn ──────────────────────────────────────────────────────
    function clockIn(projectId, projectName) {
        if (!projectId) { _showToast('Επιλέξτε έργο πρώτα!', 'error'); return; }

        const body = new FormData();
        body.append('project_id', projectId);
        body.append('csrf_token', getCsrf());

        fetch('../Backend/ClockInOut/clock_in.php', { method: 'POST', body })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    _projectId   = projectId;
                    _projectName = projectName;
                    _setUIClocked(true, projectName);
                    _startTimer(res.clock_in_time);
                    _showToast('Clock In καταχωρήθηκε! ✓', 'success');
                } else {
                    _showToast(res.message || 'Σφάλμα κατά το Clock In', 'error');
                }
            })
            .catch(() => _showToast('Σφάλμα δικτύου', 'error'));
    }

    // ── Public: clockOut ─────────────────────────────────────────────────────
    function clockOut() {
        const body = new FormData();
        body.append('csrf_token', getCsrf());

        fetch('../Backend/ClockInOut/clock_out.php', { method: 'POST', body })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const mins  = res.total_minutes;
                    const h     = Math.floor(mins / 60);
                    const m     = mins % 60;
                    _stopTimer();
                    _setUIClocked(false, null);
                    _showToast(`Clock Out! Εργάστηκες ${h > 0 ? h + 'ω ' + m + 'λ' : m + ' λεπτά'}.`, 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    _showToast(res.message || 'Σφάλμα κατά το Clock Out', 'error');
                }
            })
            .catch(() => _showToast('Σφάλμα δικτύου', 'error'));
    }

    // ── Public: isActive ─────────────────────────────────────────────────────
    function isActive() { return _clockInTime !== null; }

    // ── Self-contained toast ──────────────────────────────────────────────────
    function _showToast(msg, type) {
        const existing = document.getElementById('ct-toast');
        if (existing) existing.remove();
        const t = document.createElement('div');
        t.id = 'ct-toast';
        t.textContent = msg;
        Object.assign(t.style, {
            position    : 'fixed',
            bottom      : '80px',
            right       : '24px',
            padding     : '12px 20px',
            borderRadius: '8px',
            background  : type === 'success' ? 'var(--success,#16a34a)' : 'var(--danger,#dc2626)',
            color       : '#fff',
            fontWeight  : '600',
            fontSize    : '0.875rem',
            zIndex      : '9999',
            boxShadow   : '0 4px 12px rgba(0,0,0,.2)',
        });
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // ── Kick off on DOM ready ─────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', _restoreFromServer);

    return { clockIn, clockOut, isActive };

})();
