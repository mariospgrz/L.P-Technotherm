<?php
/**
 * dashboards/supervisor_dashboard.php
 * Supervisor (Επιβλέποντας) dashboard.
 * Tabs: Καταγραφή Ωρών | Τιμολόγια | Τιμολόγια μου | Ανάθεση Βοηθών | Αίτηση Επιπλέον Ωρών
 */
require_once __DIR__ . '/../Backend/supervisor_session.php';
require_once __DIR__ . '/../Backend/Database/Database.php';

$user_id = (int) $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['username'] ?? 'Επιβλέπων');

// ── Active tab from URL ────────────────────────────────────────────────────────
$allowed_tabs = ['hours', 'invoices', 'invoices-mine', 'assign', 'overtime'];
$active_tab = in_array($_GET['tab'] ?? '', $allowed_tabs) ? $_GET['tab'] : 'hours';

// ── Flash ──────────────────────────────────────────────────────────────────────
$flash_success = isset($_GET['success']) ? htmlspecialchars(urldecode($_GET['success'])) : '';
$flash_error = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error'])) : '';

// ── Fetch all projects (supervisor sees all) ──────────────────────────────────
$projects = [];
$res = $conn->query(
    'SELECT id, name, location, status
       FROM projects
      ORDER BY start_date DESC'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
}

// ── Fetch my invoices ──────────────────────────────────────────────────────────
$invoices = [];
$res2 = $conn->prepare(
    'SELECT i.id, i.description, i.amount, i.date, p.name AS project
       FROM invoices i
       JOIN projects p ON i.project_id = p.id
      WHERE i.uploaded_by = ?
      ORDER BY i.date DESC'
);
if ($res2) {
    $res2->bind_param('i', $user_id);
    $res2->execute();
    $result2 = $res2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $invoices[] = $row;
    }
    $res2->close();
}

// ── Fetch available helpers (role = 'helper') ──────────────────────────────────
$helpers = [];
$res3 = $conn->query(
    "SELECT id, name, username FROM users WHERE role = 'helper' ORDER BY name"
);
if ($res3) {
    while ($row = $res3->fetch_assoc()) {
        $helpers[] = $row;
    }
}

// ── Fetch current helper assignments for this supervisor's projects ────────────
$assignments = [];
if (!empty($projects)) {
    $proj_ids = implode(',', array_map(fn($p) => (int) $p['id'], $projects));
    $res4 = $conn->query(
        "SELECT pa.project_id, p.name AS project_name, u.name AS helper_name
           FROM project_assignments pa
           JOIN projects p ON pa.project_id = p.id
           JOIN users u    ON pa.user_id    = u.id
          WHERE pa.project_id IN ($proj_ids)
            AND u.role = 'helper'
          ORDER BY p.name, u.name"
    );
    if ($res4) {
        $tmp = [];
        while ($row = $res4->fetch_assoc()) {
            $tmp[$row['project_id']]['project'] = $row['project_name'];
            $tmp[$row['project_id']]['helpers'][] = $row['helper_name'];
        }
        $assignments = array_values($tmp);
    }
}

// ── Fetch overtime requests ────────────────────────────────────────────────────
$overtime = [];
$res5 = $conn->prepare(
    'SELECT o.id, p.name AS project, o.hours, o.date, o.status
       FROM overtime_requests o
       JOIN projects p ON o.project_id = p.id
      WHERE o.user_id = ?
      ORDER BY o.date DESC'
);
if ($res5) {
    $res5->bind_param('i', $user_id);
    $res5->execute();
    $result5 = $res5->get_result();
    while ($row = $result5->fetch_assoc()) {
        $overtime[] = $row;
    }
    $res5->close();
}

// ── Hours summary from time_entries ───────────────────────────────────────────
$hours_month = 0;
$hours_total = 0;

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

// ── Recent time entries ────────────────────────────────────────────────────────
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

// ── JSON for JS ───────────────────────────────────────────────────────────────
$js_projects = json_encode($projects, JSON_UNESCAPED_UNICODE);
$js_invoices = json_encode(array_map(fn($i) => [
    'id' => (int) $i['id'],
    'description' => $i['description'],
    'project' => $i['project'],
    'amount' => (float) $i['amount'],
    'date' => $i['date'],
], $invoices), JSON_UNESCAPED_UNICODE);
$js_helpers = json_encode($helpers, JSON_UNESCAPED_UNICODE);
$js_overtime = json_encode(array_map(fn($o) => [
    'id' => (int) $o['id'],
    'project' => $o['project'],
    'hours' => (float) $o['hours'],
    'date' => $o['date'],
    'status' => $o['status'],
], $overtime), JSON_UNESCAPED_UNICODE);
$js_assignments = json_encode($assignments, JSON_UNESCAPED_UNICODE);
$js_work_logs = json_encode($work_logs, JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Επιβλέποντα | LP Technotherm</title>
    <meta name="description" content="Πίνακας ελέγχου επιβλέποντα - LP Technotherm">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/supervisor.css">
    <link rel="stylesheet" href="/frontend/CSS/logout_button.css">
    <link rel="stylesheet" href="CSS/responsive.css">
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }
    </style>
</head>

<body>

    <!-- ════════════════ HEADER ════════════════ -->
    <header class="main-header">
        <div class="header-left">
            <img src="../frontend/images/images.jpg" alt="LP Technotherm" class="company-logo"
                onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'48\' height=\'48\'><rect width=\'48\' height=\'48\' fill=\'%232563eb\' rx=\'8\'/><text x=\'24\' y=\'30\' font-family=\'Arial\' font-size=\'12\' font-weight=\'bold\' fill=\'white\' text-anchor=\'middle\'>LP</text></svg>'">
            <div class="user-info">
                <h1>Πίνακας Επιβλέποντα</h1>
                <p>Καλώς ήρθατε,
                    <?= $user_name ?>
                </p>
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
        <div class="flash-banner success"><i class="fas fa-check-circle"></i>
            <?= $flash_success ?>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash-banner error"><i class="fas fa-exclamation-triangle"></i>
            <?= $flash_error ?>
        </div>
    <?php endif; ?>


    <!-- ════════════════ ALWAYS VISIBLE: STATUS CARDS ════════════════ -->
    <div class="status-cards-row">
        <div class="status-card">
            <div class="status-card-icon blue"><i class="fas fa-play"></i></div>
            <div class="status-card-body">
                <label>Κατάσταση</label>
                <span id="status-value">Εκτός Εργασίας</span>
            </div>
        </div>
        <div class="status-card">
            <div class="status-card-icon green"><i class="fas fa-clock"></i></div>
            <div class="status-card-body">
                <label>Ώρες Μήνα</label>
                <span><?= $hours_month ?> ώρες</span>
            </div>
        </div>
        <div class="status-card">
            <div class="status-card-icon purple"><i class="fas fa-clock"></i></div>
            <div class="status-card-body">
                <label>Συνολικές Ώρες</label>
                <span><?= $hours_total ?> ώρες</span>
            </div>
        </div>
    </div>

    <!-- ════════════════ ALWAYS VISIBLE: PROJECT SELECTION ═════════════ -->
    <div class="project-select-section">
        <div class="section-label">Επιλέξτε Έργο</div>
        <?php if (empty($projects)): ?>
            <div class="empty-state" style="padding:20px 0;">
                <i class="fas fa-hard-hat"></i>
                <p>Δεν υπάρχουν έργα.</p>
            </div>
        <?php else: ?>
            <div class="project-cards-grid">
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

    <!-- ════════════════ TAB BAR ════════════════ -->
    <div class="tab-bar">
        <button class="tab-btn <?= $active_tab === 'hours' ? 'active' : '' ?>" data-tab="hours">
            <i class="fas fa-clock"></i> Καταγραφή Ωρών
        </button>
        <button class="tab-btn <?= $active_tab === 'invoices' ? 'active' : '' ?>" data-tab="invoices">
            <i class="fas fa-file-invoice-dollar"></i> Τιμολόγια
        </button>
        <button class="tab-btn <?= $active_tab === 'invoices-mine' ? 'active' : '' ?>" data-tab="invoices-mine">
            <i class="fas fa-file-alt"></i> Τιμολόγια μου
        </button>
        <button class="tab-btn <?= $active_tab === 'assign' ? 'active' : '' ?>" data-tab="assign">
            <i class="fas fa-user-friends"></i> Ανάθεση Βοηθών
        </button>
        <button class="tab-btn <?= $active_tab === 'overtime' ? 'active' : '' ?>" data-tab="overtime">
            <i class="fas fa-plus-circle"></i> Αίτηση Επιπλέον Ωρών
        </button>
    </div>

    <!-- ════════════════ CONTENT AREA ════════════════ -->
    <div class="content-area">

        <!-- ══ TAB 1: ΚΑΤΑΓΡΑΦΗ ΩΡΩΝ ══════════════════════════════════ -->
        <div id="panel-hours" class="tab-panel <?= $active_tab === 'hours' ? 'active' : '' ?>">

            <!-- Clock In/Out area -->
            <div class="clock-center-area">
                <div class="clock-circle-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="clock-project-label">Επιλεγμένο Έργο</div>
                <div class="clock-project-name" id="selected-project-name">
                    <?php if (empty($projects)): ?>
                        Δεν υπάρχουν έργα
                    <?php else: ?>
                        Επιλέξτε ένα έργο για να ξεκινήσετε την καταγραφή
                    <?php endif; ?>
                </div>
                <button id="clock-toggle-btn" class="btn-clock-in" onclick="clockToggle()" <?= empty($projects) ? 'disabled' : '' ?>>
                    <i class="fas fa-play"></i> Clock In - Έναρξη Εργασίας
                </button>

                <div class="info-box" style="text-align:left;margin-top:24px;">
                    <h4><i class="fas fa-info-circle"></i> Οδηγίες</h4>
                    <ul>
                        <li>Επιλέξτε το έργο στο οποίο εργάζεστε</li>
                        <li>Πατήστε Clock In για να ξεκινήσετε την καταγραφή</li>
                        <li>Πατήστε Clock Out όταν ολοκληρώσετε την εργασία σας</li>
                    </ul>
                </div>
            </div>



            <!-- Recent work logs -->
            <div class="content-area" style="padding-top:0;">
                <div class="recent-section">
                    <h3>Πρόσφατες Εργασίες</h3>
                    <?php if (empty($work_logs)): ?>
                        <div class="empty-state" style="padding:30px 0;">
                            <i class="fas fa-calendar-times"></i>
                            <p>Δεν υπάρχουν καταγεγραμμένες εργασίες</p>
                        </div>
                    <?php else: ?>
                        <div class="work-list">
                            <?php foreach ($work_logs as $wl): ?>
                                <div class="work-item">
                                    <div class="work-item-left">
                                        <a href="#">
                                            <?= htmlspecialchars($wl['project_name']) ?>
                                        </a>
                                        <small>
                                            <?= htmlspecialchars($wl['work_date']) ?> &nbsp;
                                            <?= htmlspecialchars(substr($wl['clock_in'] ?? '', 0, 5)) ?> π.μ.
                                            έως
                                            <?= htmlspecialchars(substr($wl['clock_out'] ?? '', 0, 5)) ?> μ.μ.
                                        </small>
                                    </div>
                                    <div class="work-item-hours">
                                        <?= (float) $wl['total_hours'] ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /panel-hours -->


        <!-- ══ TAB 2: ΤΙΜΟΛΟΓΙΑ (Καταχώρηση) ══════════════════════════ -->
        <div id="panel-invoices" class="tab-panel <?= $active_tab === 'invoices' ? 'active' : '' ?>">
            <div class="card">
                <div class="card-header">
                    <div class="card-icon orange"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="card-header-text">
                        <h3>Καταχώρηση Τιμολογίου</h3>
                        <p>Προσθήκη τιμολογίου προμηθευτή</p>
                    </div>
                </div>

                <form id="invoice-form" action="actions/submit_invoice.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="inv-project">Έργο <span class="req">*</span></label>
                        <select id="inv-project" name="project_id" required>
                            <option value="" disabled selected>-- Επιλέξτε έργο --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>">
                                    <?= htmlspecialchars($proj['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="inv-supplier">Προμηθευτής <span class="req">*</span></label>
                        <input type="text" id="inv-supplier" name="supplier" placeholder="π.χ. ΤΕΧΝΙΚΗ ΑΕ" required>
                    </div>

                    <div class="form-group">
                        <label for="inv-amount">Ποσό (€) <span class="req">*</span></label>
                        <input type="number" id="inv-amount" name="amount" placeholder="π.χ. 1500" min="0.01"
                            step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label style="color:var(--primary);font-weight:600;">Φωτογραφία Τιμολογίου (προαιρετικό)</label>
                        <div class="file-upload-area">
                            <input type="file" id="invoice-photo" name="invoice_photo" accept="image/*,application/pdf">
                            <label for="invoice-photo">
                                <i class="fas fa-arrow-up-from-bracket"></i>
                                <span id="invoice-photo-label">Επιλέξτε αρχείο</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"
                        style="width:100%;justify-content:center;padding:13px;">
                        <i class="fas fa-save"></i> Καταχώρηση Τιμολογίου
                    </button>
                </form>
            </div>
        </div><!-- /panel-invoices -->


        <!-- ══ TAB 3: ΤΙΜΟΛΟΓΙΑ ΜΟΥ ════════════════════════════════════ -->
        <div id="panel-invoices-mine" class="tab-panel <?= $active_tab === 'invoices-mine' ? 'active' : '' ?>">

            <div class="invoice-search">
                <i class="fas fa-search"></i>
                <input type="text" id="invoice-search" placeholder="Αναζήτηση προμηθευτή ή έργου..."
                    oninput="filterInvoices()">
            </div>

            <div id="my-invoices-list" class="invoice-list">
                <!-- Rendered by JS from __INVOICES__ -->
                <?php if (empty($invoices)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>Δεν υπάρχουν τιμολόγια ακόμα</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <div class="invoice-item" id="inv-<?= $inv['id'] ?>">
                            <div class="invoice-icon-box"><i class="fas fa-file-alt"></i></div>
                            <div class="invoice-info">
                                <strong>
                                    <?= htmlspecialchars($inv['description']) ?>
                                </strong>
                                <small><i class="fas fa-building"></i>
                                    <?= htmlspecialchars($inv['project']) ?>
                                </small>
                            </div>
                            <div class="invoice-amount">
                                <strong>€
                                    <?= number_format((float) $inv['amount'], 2, ',', '.') ?>
                                </strong>
                                <small>
                                    <?= date('Y-m-d', strtotime($inv['date'])) ?>
                                </small>
                            </div>
                            <div class="invoice-actions">
                                <button class="icon-btn" title="Επεξεργασία" onclick="editInvoice(<?= $inv['id'] ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="icon-btn danger" title="Διαγραφή" onclick="deleteInvoice(<?= $inv['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent work logs (same section shown in prototype below invoices) -->
            <div class="recent-section">
                <h3>Πρόσφατες Εργασίες</h3>
                <?php if (empty($work_logs)): ?>
                    <div class="empty-state" style="padding:20px 0;">
                        <i class="fas fa-calendar-times"></i>
                        <p>Δεν υπάρχουν καταγεγραμμένες εργασίες</p>
                    </div>
                <?php else: ?>
                    <div class="work-list">
                        <?php foreach (array_slice($work_logs, 0, 5) as $wl): ?>
                            <div class="work-item">
                                <div class="work-item-left">
                                    <a href="#">
                                        <?= htmlspecialchars($wl['project_name']) ?>
                                    </a>
                                    <small>
                                        <?= htmlspecialchars($wl['work_date']) ?> &nbsp;
                                        <?= htmlspecialchars(substr($wl['clock_in'] ?? '', 0, 5)) ?> π.μ.
                                        έως
                                        <?= htmlspecialchars(substr($wl['clock_out'] ?? '', 0, 5)) ?> μ.μ.
                                    </small>
                                </div>
                                <div class="work-item-hours">
                                    <?= (float) $wl['total_hours'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div><!-- /panel-invoices-mine -->


        <!-- ══ TAB 4: ΑΝΑΘΕΣΗ ΒΟΗΘΩΝ ════════════════════════════════════ -->
        <div id="panel-assign" class="tab-panel <?= $active_tab === 'assign' ? 'active' : '' ?>">




            <div class="card">
                <div class="card-header">
                    <div class="card-icon purple"><i class="fas fa-user-friends"></i></div>
                    <div class="card-header-text">
                        <h3>Ανάθεση Βοηθών</h3>
                        <p>Προσθέστε βοηθούς στα έργα σας</p>
                    </div>
                </div>

                <form id="assign-form" action="actions/assign_helpers.php" method="POST">
                    <div class="form-group">
                        <label for="assign-project">Επιλέξτε Έργο <span class="req">*</span></label>
                        <select id="assign-project" name="project_id" required>
                            <option value="" disabled selected>-- Επιλέξτε έργο --</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>">
                                    <?= htmlspecialchars($proj['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($helpers)): ?>
                        <div class="form-group" style="position: relative;">
                            <label>Επιλέξτε Βοηθούς <span id="helpers-count-label"
                                    style="font-weight: normal; color: var(--text-secondary);">(0
                                    επιλεγμένοι)</span></label>

                            <div id="helpers-disabled-overlay"
                                style="position: absolute; inset: 0; top: 24px; background: rgba(255,255,255,0.6); z-index: 10; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(1px); border-radius: var(--radius-sm);">
                                <span
                                    style="background: var(--bg-card); padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
                                    <i class="fas fa-lock" style="margin-right: 6px;"></i> Επιλέξτε έργο πρώτα
                                </span>
                            </div>

                            <div class="helpers-multi-select" style="max-height: none;">
                                <?php foreach ($helpers as $h): ?>
                                    <label class="helper-card" for="h-<?= $h['id'] ?>">
                                        <input type="checkbox" id="h-<?= $h['id'] ?>" name="helper_ids[]"
                                            value="<?= $h['id'] ?>" class="helper-checkbox" onchange="updateSelectedCount()">
                                        <div class="helper-card-inner">
                                            <div class="helper-avatar">
                                                <i class="fas fa-user-plus icon-unselected"></i>
                                                <i class="fas fa-check-circle icon-selected"></i>
                                            </div>
                                            <div class="helper-info-col">
                                                <span class="helper-name"><?= htmlspecialchars($h['name']) ?></span>
                                                <span class="helper-role">Βοηθός</span>
                                            </div>
                                            <div class="helper-badge-col">
                                                <span class="helper-selected-badge">Επιλεγμένος</span>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding:20px 0;">
                            <i class="fas fa-users-slash"></i>
                            <p>Δεν υπάρχουν διαθέσιμοι βοηθοί</p>
                        </div>
                    <?php endif; ?>

                    <div class="info-box">
                        <h4><i class="fas fa-info-circle"></i> Πληροφορίες</h4>
                        <ul>
                            <li>Επιλέξτε το έργο και τους βοηθούς που θα εργαστούν</li>
                            <li>Όταν οι βοηθοί κάνουν Clock In, οι ώρες τους θα καταγράφονται στο έργο</li>
                            <li>Μπορείτε να αλλάξετε την ανάθεση ανά πάσα στιγμή</li>
                            <li>Οι βοηθοί θα βλέπουν μόνο τα έργα στα οποία είναι ανατεθειμένοι</li>
                        </ul>
                    </div>

                    <?php if (!empty($helpers)): ?>
                        <button type="submit" class="btn btn-primary"
                            style="width:100%;justify-content:center;padding:13px;">
                            <i class="fas fa-user-friends"></i> Αποθήκευση Ανάθεσης
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Current Assignments -->
            <div class="recent-section">
                <h3>Τρέχουσες Αναθέσεις</h3>
                <div id="current-assignments" class="assignments-list">
                    <?php if (empty($assignments)): ?>
                        <p style="color:var(--text-muted);font-size:0.875rem;">Δεν υπάρχουν τρέχουσες αναθέσεις.</p>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                            <div class="assignment-item">
                                <h5>
                                    <?= htmlspecialchars($a['project']) ?>
                                </h5>
                                <div class="helpers-chips">
                                    <?php foreach ($a['helpers'] as $hname): ?>
                                        <span class="helper-chip">
                                            <?= htmlspecialchars($hname) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /panel-assign -->


        <!-- ══ TAB 5: ΑΙΤΗΣΗ ΕΠΙΠΛΕΟΝ ΩΡΩΝ ══════════════════════════════ -->
        <div id="panel-overtime" class="tab-panel <?= $active_tab === 'overtime' ? 'active' : '' ?>">

            <div class="overtime-header">
                <h3>Τα Αιτήματά μου</h3>
                <button class="btn btn-primary" onclick="openModal('overtime-modal')">
                    <i class="fas fa-plus"></i> Αίτημα Υπερωρίας
                </button>
            </div>

            <div id="overtime-list" class="overtime-list">
                <?php if (empty($overtime)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>Δεν έχετε υποβάλει αιτήματα υπερωριών ακόμα</p>
                    </div>
                <?php else: ?>
                    <?php
                    $status_labels = [
                        'pending' => 'Σε αναμονή',
                        'approved' => 'Εγκρίθηκε',
                        'rejected' => 'Απορρίφθηκε',
                    ];
                    foreach ($overtime as $ot): ?>
                        <div class="overtime-item">
                            <div class="ot-left">
                                <strong>
                                    <?= htmlspecialchars($ot['project']) ?>
                                </strong>
                                <small>
                                    <?= htmlspecialchars($ot['date']) ?> ·
                                    <?= (float) $ot['hours'] ?> ώρες
                                </small>
                            </div>
                            <span class="badge <?= htmlspecialchars($ot['status']) ?>">
                                <?= htmlspecialchars($status_labels[$ot['status']] ?? $ot['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent work (also shown in prototype below overtime) -->
            <div class="recent-section">
                <h3>Πρόσφατες Εργασίες</h3>
                <?php if (empty($work_logs)): ?>
                    <div class="empty-state" style="padding:20px 0;">
                        <i class="fas fa-calendar-times"></i>
                        <p>Δεν υπάρχουν καταγεγραμμένες εργασίες</p>
                    </div>
                <?php else: ?>
                    <div class="work-list">
                        <?php foreach (array_slice($work_logs, 0, 5) as $wl): ?>
                            <div class="work-item">
                                <div class="work-item-left">
                                    <a href="#">
                                        <?= htmlspecialchars($wl['project_name']) ?>
                                    </a>
                                    <small>
                                        <?= htmlspecialchars($wl['work_date']) ?> &nbsp;
                                        <?= htmlspecialchars(substr($wl['clock_in'] ?? '', 0, 5)) ?> π.μ.
                                        έως
                                        <?= htmlspecialchars(substr($wl['clock_out'] ?? '', 0, 5)) ?> μ.μ.
                                    </small>
                                </div>
                                <div class="work-item-hours">
                                    <?= (float) $wl['total_hours'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div><!-- /panel-overtime -->

    </div><!-- /content-area -->


    <!-- ════════════════ MODAL: Overtime Request ════════════════ -->
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
                                <option value="<?= $proj['id'] ?>">
                                    <?= htmlspecialchars($proj['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ot-hours">Επιπλέον Ώρες <span class="req">*</span></label>
                        <input type="number" id="ot-hours" name="hours" placeholder="π.χ. 2.5" min="0.5" step="0.5"
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
                    <div class="modal-footer" style="padding:0;margin-top:8px;border:none;background:none;">
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
        window.__INVOICES__ = <?= $js_invoices ?>;
        window.__HELPERS__ = <?= $js_helpers ?>;
        window.__OVERTIME__ = <?= $js_overtime ?>;
        window.__ASSIGNMENTS__ = <?= $js_assignments ?>;
        window.__WORK_LOGS__ = <?= $js_work_logs ?>;
    </script>
    <script src="JS/supervisor.js"></script>

</body>

</html>