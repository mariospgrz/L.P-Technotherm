<?php
/**
 * dashboards/helper_dashboard.php
 * Πίνακας Βοηθού (Helper Dashboard)
 */
require_once __DIR__ . '/../Backend/helper_session.php';
require_once __DIR__ . '/../Backend/Database/Database.php';

$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['username'] ?? 'Βοηθός');

// ── Flash ──────────────────────────────────────────────────────────────────────
$flash_success = isset($_GET['success']) ? htmlspecialchars(urldecode($_GET['success'])) : '';
$flash_error = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error'])) : '';

// ── Assigned projects (only projects assigned to this helper) ──────────────────
$projects = [];
$resP = $conn->prepare(
    'SELECT p.id, p.name, p.location
       FROM projects p
       JOIN project_assignments pa ON pa.project_id = p.id
      WHERE pa.user_id = ?
      ORDER BY p.name'
);
if ($resP) {
    $resP->bind_param('i', $user_id);
    $resP->execute();
    $resultP = $resP->get_result();
    while ($row = $resultP->fetch_assoc()) {
        $projects[] = $row;
    }
    $resP->close();
}

// ── Hours summary ──────────────────────────────────────────────────────────────
$hours_month = 0.00;
$hours_total = 0.00;
$resH = $conn->prepare(
    'SELECT
        ROUND(SUM(TIMESTAMPDIFF(MINUTE, clock_in, clock_out)) / 60, 2) AS hours_total,
        ROUND(SUM(CASE WHEN MONTH(`date`)=MONTH(CURDATE()) AND YEAR(`date`)=YEAR(CURDATE())
              THEN TIMESTAMPDIFF(MINUTE, clock_in, clock_out) / 60 ELSE 0 END), 2) AS hours_month
       FROM time_entries
      WHERE user_id = ? AND clock_out IS NOT NULL'
);
if ($resH) {
    $resH->bind_param('i', $user_id);
    $resH->execute();
    $rh = $resH->get_result()->fetch_assoc();
    $hours_total = (float) ($rh['hours_total'] ?? 0);
    $hours_month = (float) ($rh['hours_month'] ?? 0);
    $resH->close();
}

// ── Recent work logs ───────────────────────────────────────────────────────────
$work_logs = [];
$resW = $conn->prepare(
    'SELECT p.name AS project_name, te.date AS work_date, te.clock_in, te.clock_out,
            ROUND(TIMESTAMPDIFF(MINUTE, te.clock_in, te.clock_out) / 60, 2) AS total_hours
       FROM time_entries te
       JOIN projects p ON te.project_id = p.id
      WHERE te.user_id = ? AND te.clock_out IS NOT NULL
      ORDER BY te.date DESC, te.clock_in DESC
      LIMIT 20'
);
if ($resW) {
    $resW->bind_param('i', $user_id);
    $resW->execute();
    $resultW = $resW->get_result();
    while ($row = $resultW->fetch_assoc()) {
        $work_logs[] = $row;
    }
    $resW->close();
}

// ── Overtime requests ──────────────────────────────────────────────────────────
$overtime = [];
$resO = $conn->prepare(
    'SELECT o.id, p.name AS project, o.hours, o.date, o.description AS reason, o.status,
            DATE_FORMAT(o.created_at, \'%d %b %Y, %H:%i\') AS submitted
       FROM overtime_requests o
       JOIN projects p ON o.project_id = p.id
      WHERE o.user_id = ?
      ORDER BY o.date DESC'
);
if ($resO) {
    $resO->bind_param('i', $user_id);
    $resO->execute();
    $resultO = $resO->get_result();
    while ($row = $resultO->fetch_assoc()) {
        $overtime[] = $row;
    }
    $resO->close();
}

$status_labels = [
    'pending' => 'Σε αναμονή',
    'approved' => 'Εγκρίθηκε',
    'rejected' => 'Απορρίφθηκε',
];

$js_projects = json_encode($projects, JSON_UNESCAPED_UNICODE);
$js_work_logs = json_encode($work_logs, JSON_UNESCAPED_UNICODE);
$js_overtime = json_encode($overtime, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Βοηθού | LP Technotherm</title>
    <meta name="description" content="Πίνακας ελέγχου βοηθού - LP Technotherm">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="CSS/helper.css">
    <link rel="icon" type="image/jpeg" href="/frontend/images/images.jpg">
</head>

<body>

    <!-- ════════════════ HEADER ════════════════ -->
    <header class="main-header">
        <div class="header-left">
            <img src="../frontend/images/images.jpg" alt="LP Technotherm" class="company-logo"
                onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'48\' height=\'48\'><rect width=\'48\' height=\'48\' fill=\'%232563eb\' rx=\'8\'/><text x=\'24\' y=\'30\' font-family=\'Arial\' font-size=\'12\' font-weight=\'bold\' fill=\'white\' text-anchor=\'middle\'>LP</text></svg>'">
            <div class="user-info">
                <h1>Πίνακας Βοηθού</h1>
                <p>Καλώς ήρθατε, <?= $user_name ?></p>
            </div>
        </div>
        <div class="header-right">
            <button class="btn-logout" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                Αποσύνδεση
            </button>
        </div>
    </header>

    <!-- ════════════════ FLASH ════════════════ -->
    <?php if ($flash_success): ?>
        <div class="flash-banner success"><i class="fas fa-check-circle"></i> <?= $flash_success ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash-banner error"><i class="fas fa-exclamation-triangle"></i> <?= $flash_error ?></div>
    <?php endif; ?>

    <!-- ════════════════ PAGE CONTENT ════════════════ -->
    <div class="page-wrapper">

        <!-- ── 1. STATS ROW ───────────────────────────────────── -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-clock"></i></div>
                <div class="stat-body">
                    <label>Ώρες Μήνα</label>
                    <span><?= number_format($hours_month, 2) ?> ώρες</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-clock"></i></div>
                <div class="stat-body">
                    <label>Συνολικές Ώρες</label>
                    <span><?= number_format($hours_total, 2) ?> ώρες</span>
                </div>
            </div>
        </div>

        <!-- ── 2. ΑΝΑΤΕΘΕΙΜΕΝΑ ΕΡΓΑ ──────────────────────────── -->
        <div class="section-card">
            <div class="section-title">Ανατεθειμένα Έργα</div>
            <?php if (empty($projects)): ?>
                <div class="empty-state">
                    <i class="fas fa-hard-hat"></i>
                    <p>Δεν έχουν ανατεθεί έργα ακόμα.</p>
                </div>
            <?php else: ?>
                <div class="project-list">
                    <?php foreach ($projects as $proj): ?>
                        <div class="project-card" data-id="<?= $proj['id'] ?>" onclick="selectProject(<?= $proj['id'] ?>)">
                            <h4><?= htmlspecialchars($proj['name']) ?></h4>
                            <div class="proj-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($proj['location'] ?? '—') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── 3. ΚΑΤΑΓΡΑΦΗ ΩΡΩΝ ──────────────────────────────── -->
        <div class="section-card" id="clock-section">
            <div class="section-title">Καταγραφή Ωρών</div>

            <div class="clock-center-area">
                <div class="clock-circle-icon">
                    <i class="fas fa-clock"></i>
                </div>

                <!-- Live timer display -->
                <div id="clock-timer-display" class="clock-timer-display">00:00:00</div>

                <div class="clock-project-label">Επιλεγμένο Έργο</div>
                <div class="clock-project-name" id="selected-project-name">
                    <?php if (empty($projects)): ?>
                        Δεν υπάρχουν ανατεθειμένα έργα
                    <?php elseif (count($projects) === 1): ?>
                        <?= htmlspecialchars($projects[0]['name']) ?>
                    <?php else: ?>
                        Επιλέξτε ένα έργο για να ξεκινήσετε
                    <?php endif; ?>
                </div>

                <button id="clock-toggle-btn" class="btn-clock-in" onclick="clockToggle()" <?= empty($projects) ? 'disabled' : '' ?>>
                    <i class="fas fa-play"></i> Clock In - Έναρξη Εργασίας
                </button>

                <!-- 8-hour warning banner (hidden by default) -->
                <div id="clock-warning-banner" class="clock-warning-banner" style="display:none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Προσοχή! Εργάζεστε πάνω από 7.5 ώρες. Θα γίνει αυτόματη αποσύνδεση σε 30 λεπτά.</span>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Οδηγίες</h4>
                    <ul>
                        <li>Επιλέξτε το έργο στο οποίο εργάζεστε</li>
                        <li>Πατήστε Clock In για να ξεκινήσετε την καταγραφή</li>
                        <li>Πατήστε Clock Out όταν ολοκληρώσετε την εργασία σας</li>
                        <li>Ο χρονόμετρο συνεχίζεται ακόμα και αν αποσυνδεθείτε</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ── 4. ΠΡΟΣΦΑΤΕΣ ΕΡΓΑΣΙΕΣ ──────────────────────────── -->
        <div class="section-card">
            <div class="section-title">Πρόσφατες Εργασίες</div>
            <?php if (empty($work_logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Δεν υπάρχουν καταγεγραμμένες εργασίες</p>
                </div>
            <?php else: ?>
                <div class="work-list-scroll-wrapper">
                    <div class="work-list" id="work-list-inner">
                        <?php
                        /**
                         * Convert a MySQL DATETIME or TIME string → "H:MM π.μ./μ.μ."
                         * Handles both "HH:MM:SS" and "YYYY-MM-DD HH:MM:SS"
                         */
                        function fmtClockTime(string $raw): string
                        {
                            if ($raw === '')
                                return '—';
                            // Full DATETIME → extract time portion after the space
                            $timePart = str_contains($raw, ' ') ? explode(' ', $raw, 2)[1] : $raw;
                            $parts = explode(':', $timePart);
                            if (count($parts) < 2 || !is_numeric($parts[0]))
                                return '—';
                            $h = (int) $parts[0];
                            $m = $parts[1];
                            $lbl = $h < 12 ? 'π.μ.' : 'μ.μ.';
                            $h24 = $h % 24;
                            return sprintf('%d:%s %s', $h24, $m, $lbl);
                        }
                        ?>
                        <?php foreach ($work_logs as $wl): ?>
                            <?php
                            $ci_display = fmtClockTime($wl['clock_in'] ?? '');
                            $co_display = fmtClockTime($wl['clock_out'] ?? '');
                            ?>
                            <div class="work-item">
                                <div class="work-item-left">
                                    <strong><?= htmlspecialchars($wl['project_name']) ?></strong>
                                    <small>
                                        <?= htmlspecialchars($wl['work_date']) ?> &nbsp;
                                        <?= htmlspecialchars($ci_display) ?> έως
                                        <?= htmlspecialchars($co_display) ?>
                                    </small>
                                </div>
                                <div class="work-item-hours"><?= number_format((float) $wl['total_hours'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- ── 5. ΣΗΜΕΙΩΣΗ ────────────────────────────────────── -->
        <div class="note-box">
            <strong>Σημείωση</strong>
            <p>Μπορείτε να βλέπετε μόνο τις ώρες εργασίας σας. Για οικονομικά στοιχεία και budget επικοινωνήστε με τον
                διαχειριστή.</p>
        </div>

        <!-- ── 6. ΑΙΤΗΜΑΤΑ ΕΠΙΠΛΕΟΝ ΩΡΩΝ ─────────────────────── -->
        <div class="section-card">
            <div class="overtime-header">
                <h3>Αιτήματα Επιπλέον Ωρών</h3>
                <button class="btn btn-primary" onclick="openModal('overtime-modal')">
                    <i class="fas fa-plus"></i> Αίτημα Υπερωρίας
                </button>
            </div>

            <?php if (empty($overtime)): ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <p>Δεν έχετε υποβάλει αιτήματα υπερωριών ακόμα</p>
                </div>
            <?php else: ?>
                <div class="overtime-list">
                    <?php foreach ($overtime as $ot): ?>
                        <div class="overtime-item">
                            <div class="ot-top">
                                <div class="ot-meta">
                                    <div class="ot-meta-item">
                                        <label>Ημερομηνία</label>
                                        <span>
                                            <?php
                                            $d = DateTime::createFromFormat('Y-m-d', $ot['date']);
                                            $days = ['Mon' => 'Δευ', 'Tue' => 'Τρι', 'Wed' => 'Τετ', 'Thu' => 'Πέμ', 'Fri' => 'Παρ', 'Sat' => 'Σάβ', 'Sun' => 'Κυρ'];
                                            $months = ['Jan' => 'Ιαν', 'Feb' => 'Φεβ', 'Mar' => 'Μαρ', 'Apr' => 'Απρ', 'May' => 'Μαΐ', 'Jun' => 'Ιουν', 'Jul' => 'Ιουλ', 'Aug' => 'Αυγ', 'Sep' => 'Σεπ', 'Oct' => 'Οκτ', 'Nov' => 'Νοε', 'Dec' => 'Δεκ'];
                                            if ($d) {
                                                echo ($days[$d->format('D')] ?? $d->format('D')) . ' '
                                                    . $d->format('d') . ' '
                                                    . ($months[$d->format('M')] ?? $d->format('M')) . ' '
                                                    . $d->format('Y');
                                            } else {
                                                echo htmlspecialchars($ot['date']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="ot-meta-item">
                                        <label>Ώρες</label>
                                        <span>
                                            <i class="fas fa-clock" style="color:var(--primary);font-size:0.8rem;"></i>
                                            <?= (float) $ot['hours'] ?>h
                                        </span>
                                    </div>
                                </div>
                                <span class="badge <?= htmlspecialchars($ot['status']) ?>">
                                    <i
                                        class="fas fa-<?= $ot['status'] === 'pending' ? 'clock' : ($ot['status'] === 'approved' ? 'check' : 'times') ?>"></i>
                                    <?= htmlspecialchars($status_labels[$ot['status']] ?? $ot['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($ot['reason'])): ?>
                                <div class="ot-reason">
                                    <strong>Αιτιολογία:</strong>
                                    <?= htmlspecialchars($ot['reason']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($ot['submitted'])): ?>
                                <div class="ot-submitted">
                                    Υποβλήθηκε: <?= htmlspecialchars($ot['submitted']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /page-wrapper -->


    <!-- ════════════════ MODAL: Αίτημα Υπερωρίας ════════════════ -->
    <div id="overtime-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i> Αίτημα Υπερωρίας
                </h3>
                <button class="close-modal" onclick="closeModal('overtime-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="overtime-form" action="actions/submit_overtime.php" method="POST">
                    <div class="form-group">
                        <label for="ot-project">Έργο <span class="req">*</span></label>
                        <select id="ot-project" name="project_id" required>
                            <option value="" disabled selected>-- Επιλέξτε έργο --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ot-hours">Επιπλέον Ώρες <span class="req">*</span></label>
                        <input type="number" id="ot-hours" name="hours" placeholder="π.χ. 2" min="0.5" step="0.5"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="ot-date">Ημερομηνία <span class="req">*</span></label>
                        <input type="date" id="ot-date" name="request_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="ot-reason">Αιτιολογία</label>
                        <textarea id="ot-reason" name="reason" rows="3"
                            placeholder="Περιγράψτε τον λόγο για τις επιπλέον ώρες..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline"
                            onclick="closeModal('overtime-modal')">Ακύρωση</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Υποβολή
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════════ HELP BUTTON ════════════════ -->
    <button class="help-btn" title="Βοήθεια">?</button>

    <!-- ════════════════ PHP → JS data injection ════════════════ -->
    <script>
        window.__PROJECTS__ = <?= $js_projects ?>;
        window.__WORK_LOGS__ = <?= $js_work_logs ?>;
        window.__OVERTIME__ = <?= $js_overtime ?>;
    </script>
    <script src="JS/clock_timer.js"></script>
    <script src="JS/helper.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Auto-select if only one project assigned
            const cards = document.querySelectorAll('.project-card');
            if (cards.length === 1) {
                selectProject(cards[0].dataset.id);
            }
        });
    </script>
    <script src="JS/push_init.js"></script>

</body>

</html>