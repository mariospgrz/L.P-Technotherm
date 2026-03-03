<?php
/**
 * frontend/admin_dashboard.php
 * Admin-only dashboard.
 * Uses the UI from Admin dashboard.html + admin.js + admin dashboard.css
 * with real PHP session guard, DB user management, and flash messages.
 */
require_once __DIR__ . '/../Backend/admin_session.php';
require_once __DIR__ . '/../Backend/Database/Database.php';

// ── Flash messages & active tab from URL ─────────────────────────────────────
$flash_success = isset($_GET['success']) ? htmlspecialchars(urldecode($_GET['success'])) : '';
$flash_error = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error'])) : '';
$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'projects') ? 'projects' : 'users';

// ── Fetch all users for User Management tab ───────────────────────────────────
$users = [];
$res = $conn->query(
    'SELECT id, username, name, role, email, `Phone number` AS phone, hourly_rate, created_at
       FROM users
      ORDER BY created_at DESC'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

$logged_in_id = (int) $_SESSION['user_id'];
$logged_in_username = htmlspecialchars($_SESSION['username']);

$role_labels = [
    'administrator' => 'Διαχειριστής',
    'supervisor' => 'Υπεύθυνος',
    'helper' => 'Βοηθός',
];

// Build employees JSON for admin.js appState (real DB data)
$employees_json = json_encode(array_map(fn($u) => [
    'name' => $u['name'] ?? '',
    'role' => $u['role'] ?? '',
    'rate' => (float) ($u['hourly_rate'] ?? 0),
    'hours' => 0,
], $users));
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Διαχειριστή | LP Technotherm</title>
    <meta name="description" content="Πίνακας ελέγχου διαχειριστή - LP Technotherm">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Admin CSS (existing) -->
    <link rel="stylesheet" href="admin dashboard.css">
    <style>
        /* ── Top-level tab bar (Users vs Projects) ── */
        .main-tab-bar {
            display: flex;
            gap: 0;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
        }

        .main-tab-btn {
            padding: 12px 28px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }

        .main-tab-btn:hover {
            color: var(--primary);
        }

        .main-tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* ── Flash banner ── */
        .flash-banner {
            padding: 13px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .flash-banner.success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .flash-banner.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* ── User Management panel ── */
        .users-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 28px;
        }

        @media (max-width: 860px) {
            .users-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 22px;
        }

        .panel-box h3 {
            margin: 0 0 16px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-box.danger-box {
            border-color: #fca5a5;
            background: #fff8f8;
        }

        .panel-box h3 .icon-primary {
            color: var(--primary);
        }

        .panel-box.danger-box h3 .icon-primary {
            color: var(--danger);
        }

        /* Form fields inside panels */
        .panel-form .form-group {
            margin-bottom: 12px;
        }

        .panel-form label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .panel-form input,
        .panel-form select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--text-main);
            background: #f8fafc;
            transition: border-color 0.2s, background 0.2s;
            box-sizing: border-box;
            font-family: inherit;
        }

        .panel-form input:focus,
        .panel-form select:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
        }

        .panel-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        /* Confirm row for delete */
        .confirm-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 14px;
            margin-top: 4px;
        }

        .confirm-row input[type="checkbox"] {
            margin-top: 3px;
            accent-color: var(--danger);
            flex-shrink: 0;
        }

        .confirm-row label {
            font-size: 0.85rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        /* Submit buttons */
        .btn-panel {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: opacity 0.2s;
        }

        .btn-panel:hover {
            opacity: 0.88;
        }

        .btn-panel-primary {
            background: var(--primary);
            color: white;
        }

        .btn-panel-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        /* Users table section */
        .section-heading {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 24px 0 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-heading i {
            color: var(--primary);
        }

        .section-heading .count-pill {
            margin-left: auto;
            background: #eff6ff;
            color: var(--primary);
            font-size: 0.78rem;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .data-table tbody tr:hover {
            background: #f8faff;
        }

        .data-table .badge-administrator {
            background: #ede9fe;
            color: #6d28d9;
        }

        .data-table .badge-supervisor {
            background: #e0f2fe;
            color: #0369a1;
        }

        .data-table .badge-helper {
            background: #dcfce7;
            color: #15803d;
        }

        .you-badge {
            background: #fef3c7;
            color: #92400e;
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: 6px;
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            color: var(--text-muted);
            padding: 32px;
            font-size: 0.95rem;
        }

        .overflow-x {
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <div class="app-container">

        <!-- ========== HEADER ========== -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-container">
                    <img src="images/images.jpg" alt="LP Technotherm"
                        onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'150\' height=\'45\'><rect width=\'150\' height=\'45\' fill=\'%232563eb\' rx=\'6\'/><text x=\'75\' y=\'28\' font-family=\'Arial\' font-size=\'13\' font-weight=\'bold\' fill=\'white\' text-anchor=\'middle\'>LP Technotherm</text></svg>'"
                        class="company-logo">
                </div>
                <div class="user-info">
                    <h1>Πίνακας Ελέγχου Διαχειριστή</h1>
                    <p>Καλώς ήρθατε, <span id="account-name">
                            <?= $logged_in_username ?>
                        </span></p>
                </div>
            </div>
            <div class="header-right">
                <button class="logout-link" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i> Αποσύνδεση
                </button>
            </div>
        </header>

        <!-- ========== FLASH MESSAGES ========== -->
        <?php if ($flash_success): ?>
            <div class="flash-banner success">
                <i class="fas fa-check-circle"></i>
                <?= $flash_success ?>
            </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="flash-banner error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $flash_error ?>
            </div>
        <?php endif; ?>

        <!-- ========== TOP-LEVEL TAB BAR ========== -->
        <div class="main-tab-bar">
            <button class="main-tab-btn <?= $active_tab === 'users' ? 'active' : '' ?>" id="mainTabUsers"
                onclick="showMainTab('users')">
                <i class="fas fa-users-cog"></i> Διαχείριση Χρηστών
            </button>
            <button class="main-tab-btn <?= $active_tab === 'projects' ? 'active' : '' ?>" id="mainTabProjects"
                onclick="showMainTab('projects')">
                <i class="fas fa-chart-bar"></i> Επισκόπηση Έργων
            </button>
        </div>

        <!-- ===================================================== -->
        <!-- TAB A: USER MANAGEMENT                                -->
        <!-- ===================================================== -->
        <div id="panel-users" style="display:<?= $active_tab === 'users' ? 'block' : 'none' ?>;">

            <div class="users-grid">

                <!-- CREATE USER -->
                <div class="panel-box">
                    <h3><i class="fas fa-user-plus icon-primary"></i> Δημιουργία Νέου Χρήστη</h3>
                    <form class="panel-form" action="/Backend/CreateUser/create_user.php" method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cu_username">Username *</label>
                                <input type="text" id="cu_username" name="username" placeholder="π.χ. nikos123" required
                                    autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="cu_password">Κωδικός * (min. 6)</label>
                                <input type="password" id="cu_password" name="password"
                                    placeholder="Τουλάχιστον 6 χαρακτήρες" minlength="6" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cu_full_name">Ονοματεπώνυμο *</label>
                            <input type="text" id="cu_full_name" name="full_name" placeholder="π.χ. Νίκος Παπαδόπουλος"
                                required autocomplete="off">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cu_role">Ρόλος *</label>
                                <select id="cu_role" name="role" required>
                                    <option value="" disabled selected>Επιλογή ρόλου</option>
                                    <option value="administrator">Διαχειριστής</option>
                                    <option value="supervisor">Υπεύθυνος</option>
                                    <option value="helper">Βοηθός</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cu_hourly_rate">Ωρομίσθιο (€)</label>
                                <input type="number" id="cu_hourly_rate" name="hourly_rate" placeholder="π.χ. 15.00"
                                    min="0" step="0.01">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cu_phone">Τηλέφωνο *</label>
                                <input type="text" id="cu_phone" name="phone" placeholder="π.χ. 6912345678" required>
                            </div>
                            <div class="form-group">
                                <label for="cu_email">Email *</label>
                                <input type="email" id="cu_email" name="email" placeholder="π.χ. nikos@gmail.com"
                                    required>
                            </div>
                        </div>
                        <button type="submit" class="btn-panel btn-panel-primary">
                            <i class="fas fa-plus"></i> Δημιουργία Χρήστη
                        </button>
                    </form>
                </div>

                <!-- DELETE USER -->
                <div class="panel-box danger-box">
                    <h3><i class="fas fa-user-minus icon-primary"></i> Διαγραφή Χρήστη</h3>
                    <form class="panel-form" action="/Backend/DeleteUser/delete_user.php" method="POST">
                        <div class="form-group">
                            <label for="du_username">Χρήστης προς διαγραφή *</label>
                            <select id="du_username" name="username" required>
                                <option value="" disabled selected>Επιλέξτε χρήστη…</option>
                                <?php foreach ($users as $u): ?>
                                    <?php if ((int) $u['id'] !== $logged_in_id): ?>
                                        <option value="<?= htmlspecialchars($u['username']) ?>">
                                            <?= htmlspecialchars(($u['name'] ?: $u['username']) . ' (@' . $u['username'] . ')') ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="confirm-row">
                            <input type="checkbox" id="du_confirm" name="confirm" required>
                            <label for="du_confirm">
                                Επιβεβαιώνω ότι θέλω να διαγράψω <strong>οριστικά</strong> αυτόν τον χρήστη.
                            </label>
                        </div>
                        <button type="submit" class="btn-panel btn-panel-danger">
                            <i class="fas fa-trash-alt"></i> Οριστική Διαγραφή
                        </button>
                    </form>
                </div>

            </div><!-- /users-grid -->

            <!-- USERS TABLE -->
            <div class="section-heading">
                <i class="fas fa-list-ul"></i> Κατάλογος Χρηστών
                <span class="count-pill">
                    <?= count($users) ?> χρήστες
                </span>
            </div>
            <div class="overflow-x">
                <?php if (empty($users)): ?>
                    <div class="no-data"><i class="fas fa-users-slash"></i> Δεν βρέθηκαν χρήστες.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ονοματεπώνυμο</th>
                                <th>Username</th>
                                <th>Ρόλος</th>
                                <th>Email</th>
                                <th>Τηλέφωνο</th>
                                <th>Ωρομίσθιο</th>
                                <th>Εγγραφή</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $i => $u):
                                $isMe = ((int) $u['id'] === $logged_in_id); ?>
                                <tr <?= $isMe ? 'style="background:#fffbeb;"' : '' ?>>
                                    <td>
                                        <?= $i + 1 ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($u['name'] ?? '—') ?>
                                        <?php if ($isMe): ?>
                                            <span class="you-badge">Εσείς</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($u['role']) ?>">
                                            <?= htmlspecialchars($role_labels[$u['role']] ?? $u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($u['email'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($u['phone'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <?= $u['hourly_rate'] !== null ? '€' . number_format((float) $u['hourly_rate'], 2) : '—' ?>
                                    </td>
                                    <td>
                                        <?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div><!-- /panel-users -->

        <!-- ===================================================== -->
        <!-- TAB B: PROJECT OVERVIEW  (original Admin dashboard UI)-->
        <!-- ===================================================== -->
        <div id="panel-projects" style="display:<?= $active_tab === 'projects' ? 'block' : 'none' ?>;">

            <!-- KPI CARDS -->
            <section class="kpi-grid">
                <div class="kpi-card">
                    <div class="icon-box blue"><i class="fas fa-chart-bar"></i></div>
                    <div class="kpi-info">
                        <h4>Συνολικός Προϋπολογισμός</h4>
                        <h2 id="stat-budget">€0</h2>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="icon-box orange"><i class="fas fa-clock"></i></div>
                    <div class="kpi-info">
                        <h4>Συνολικό Κόστος</h4>
                        <h2 id="stat-cost">€0</h2>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="icon-box green"><i class="fas fa-chart-line"></i></div>
                    <div class="kpi-info">
                        <h4>Συνολικό Κέρδος</h4>
                        <h2 id="stat-profit">€0</h2>
                    </div>
                </div>
            </section>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Αναζήτηση έργου (όνομα ή τοποθεσία)..."
                        onkeyup="filterContent()">
                </div>
                <div class="btn-group">
                    <button class="btn btn-blue" onclick="toggleModal('projectModal')">
                        <i class="fas fa-plus"></i> Νέο Έργο
                    </button>
                    <button class="btn btn-green" onclick="toggleModal('workerModal')">
                        <i class="fas fa-user-plus"></i> Νέος Υπάλληλος
                    </button>
                </div>
            </div>

            <!-- PROJECT SUB-TABS -->
            <nav class="tabs-nav">
                <button class="tab-link active" onclick="switchView('active-projects')">Ενεργά Έργα</button>
                <button class="tab-link" onclick="switchView('completed-projects')">Ολοκληρωμένα Έργα</button>
                <button class="tab-link" onclick="switchView('invoices')">Τιμολόγια</button>
                <button class="tab-link" onclick="switchView('employees')">Υπάλληλοι (
                    <?= count($users) ?>)
                </button>
                <button class="tab-link" onclick="switchView('overtime')">Αιτήσεις Επιπλέον Ωρών</button>
            </nav>

            <main id="mainContent"></main>

        </div><!-- /panel-projects -->


        <!-- ========== MODALS ========== -->

        <!-- Modal: Νέο Έργο -->
        <div id="projectModal" class="modal-overlay">
            <div class="modal-container" style="max-width:400px;">
                <div class="modal-header">
                    <h3>Δημιουργία Νέου Έργου</h3>
                    <button class="close-modal" onclick="toggleModal('projectModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" id="projName" placeholder="Όνομα Έργου"
                        style="width:100%;margin-bottom:10px;padding:10px;">
                    <input type="text" id="projLocation" placeholder="Τοποθεσία"
                        style="width:100%;margin-bottom:20px;padding:10px;">
                    <button class="btn btn-blue" onclick="saveProject()" style="width:100%;">Αποθήκευση</button>
                </div>
            </div>
        </div>

        <!-- Modal: Νέος Υπάλληλος -->
        <div id="workerModal" class="modal-overlay">
            <div class="modal-container" style="max-width:400px;">
                <div class="modal-header">
                    <h3>Προσθήκη Υπαλλήλου</h3>
                    <button class="close-modal" onclick="toggleModal('workerModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" id="workName" placeholder="Ονοματεπώνυμο"
                        style="width:100%;margin-bottom:10px;padding:10px;">
                    <select id="workRole" style="width:100%;margin-bottom:10px;padding:10px;">
                        <option value="administrator">Διαχειριστής</option>
                        <option value="supervisor">Υπεύθυνος</option>
                        <option value="helper">Βοηθός</option>
                    </select>
                    <input type="number" id="workRate" placeholder="Ωρομίσθιο (€)"
                        style="width:100%;margin-bottom:20px;padding:10px;">
                    <button class="btn btn-green" onclick="saveWorker()" style="width:100%;">Αποθήκευση</button>
                </div>
            </div>
        </div>

        <!-- Modal: Λεπτομέρειες Έργου -->
        <div id="detailsModal" class="modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-title">
                        <h2 id="modalProjName"></h2>
                        <p><i class="fas fa-map-marker-alt"></i> <span id="modalProjLoc"></span>
                            | <i class="fas fa-calendar"></i> <span id="modalProjDate"></span></p>
                    </div>
                    <button class="close-modal" onclick="closeDetails()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="details-kpi-grid">
                        <div class="d-card"><span>Συνολικός Προϋπολογισμός</span>
                            <h3 id="det-budget"></h3>
                        </div>
                        <div class="d-card green-bg"><span>Σύνολο Πληρωμών</span>
                            <h3 id="det-paid"></h3>
                        </div>
                        <div class="d-card red-bg"><span>Οφειλή Πελάτη</span>
                            <h3 id="det-owed"></h3>
                        </div>
                        <div class="d-card green-bg"><span>Κέρδος</span>
                            <h3 id="det-profit"></h3>
                        </div>
                    </div>

                    <div class="payment-summary-box">
                        <h4><i class="fas fa-file-invoice-dollar"></i> Σύνοψη Πληρωμών</h4>
                        <div class="progress-section">
                            <div class="prog-item">
                                <span>Συνολικό Τιμολόγιο: <strong id="ps-total"></strong></span>
                                <div class="bar">
                                    <div class="fill blue" style="width:100%"></div>
                                </div>
                            </div>
                            <div class="prog-item">
                                <span>Εισπραχθέντα: <strong class="text-green" id="ps-paid"></strong></span>
                                <div class="bar">
                                    <div class="fill green" id="bar-paid" style="width:0%"></div>
                                </div>
                                <small id="ps-paid-pct"></small>
                            </div>
                            <div class="prog-item">
                                <span>Προς Είσπραξη: <strong class="text-red" id="ps-owed"></strong></span>
                                <div class="bar">
                                    <div class="fill red" id="bar-owed" style="width:0%"></div>
                                </div>
                                <small id="ps-owed-pct"></small>
                            </div>
                        </div>
                    </div>

                    <div class="management-grid">
                        <div class="m-box">
                            <h4><i class="fas fa-cash-register"></i> Καταχώρηση Πληρωμής</h4>
                            <input type="text" id="payInv" placeholder="Αριθμός Τιμολογίου (π.χ. ΤΙΜ-001)">
                            <input type="number" id="payAmt" placeholder="Ποσό Πληρωμής (€)">
                            <button class="btn btn-dark" onclick="addPaymentRecord()">
                                <i class="fas fa-plus"></i> Προσθήκη Πληρωμής
                            </button>
                            <h5>Ιστορικό Πληρωμών</h5>
                            <ul id="paymentHistory" class="history-list"></ul>
                        </div>
                        <div class="m-box">
                            <h4><i class="fas fa-edit"></i> Αναπροσαρμογή Προϋπολογισμού</h4>
                            <input type="number" id="adjAmt" placeholder="Επιπλέον Ποσό (€)">
                            <p class="hint">Θετικό για επιπλέον έργα, αρνητικό για μειώσεις</p>
                            <textarea id="adjDesc" placeholder="Περιγραφή (προαιρετικό)"></textarea>
                            <button class="btn btn-outline" onclick="adjustBudget()">
                                <i class="fas fa-plus"></i> Ενημέρωση Προϋπολογισμού
                            </button>
                            <h5>Ιστορικό Αναπροσαρμογών</h5>
                            <ul id="adjHistory" class="history-list"></ul>
                        </div>
                    </div>

                    <div class="cost-footer">
                        <h4><i class="fas fa-dollar-sign"></i> Ανάλυση Κόστους</h4>
                        <div class="cost-pills">
                            <div class="pill blue-pill">Κόστος Εργατοωρών: <strong id="det-labor"></strong></div>
                            <div class="pill orange-pill">Κόστος Υλικών: <strong id="det-materials"></strong></div>
                            <div class="pill purple-pill">Συνολικό Κόστος: <strong id="det-total-cost"></strong></div>
                        </div>
                    </div>

                    <div style="margin-top:20px;text-align:right;">
                        <button class="btn btn-blue" onclick="openReport()">Αναφορά Έργου</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Αναφορά Έργου -->
        <div id="reportModal" class="modal-overlay">
            <div class="modal-container report-container">
                <div class="modal-header">
                    <div>
                        <h2>Αναφορά Έργου</h2>
                        <p id="reportProjName"></p>
                    </div>
                    <button class="close-modal" onclick="closeReport()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
                        <div class="kpi-card"><span>Προϋπολογισμός</span>
                            <h3 id="rep-budget"></h3>
                        </div>
                        <div class="kpi-card"><span>Συνολικό Κόστος</span>
                            <h3 id="rep-cost"></h3>
                        </div>
                        <div class="kpi-card"><span>Κέρδος/Ζημία</span>
                            <h3 id="rep-profit"></h3>
                        </div>
                        <div class="kpi-card"><span>Ποσοστό</span>
                            <h3 id="rep-pct"></h3>
                        </div>
                    </div>
                    <div class="analytics-grid">
                        <div class="chart-box">
                            <h4>Κατανομή Κόστους</h4>
                            <canvas id="costPieChart" style="max-height:200px;"></canvas>
                        </div>
                        <div class="chart-box">
                            <h4>Εργατοώρες ανά Άτομο</h4>
                            <canvas id="laborBarChart" style="max-height:200px;"></canvas>
                        </div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Όνομα</th>
                                <th>Ρόλος</th>
                                <th>Ώρες</th>
                                <th>Κόστος</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /app-container -->

    <!-- ── Inject real DB employees into appState BEFORE admin.js loads ── -->
    <script>
        // PHP passes real users so admin.js Employees tab shows live data
        window.__DB_EMPLOYEES__ = <?= $employees_json ?>;
    </script>

    <!-- Admin JS (existing — unchanged except handleLogout is overridden below) -->
    <script src="admin.js"></script>

    <script>
        // ── Override: real logout ─────────────────────────────────────────────────────
        function handleLogout() {
            if (confirm('Αποσύνδεση;')) {
                window.location.href = '/Backend/logout.php';
            }
        }

        // ── Replace appState.employees with real DB data ───────────────────────────────
        if (window.__DB_EMPLOYEES__ && window.__DB_EMPLOYEES__.length) {
            appState.employees = window.__DB_EMPLOYEES__;
        }

        // ── Top-level tab switching (Users ↔ Projects) ────────────────────────────────
        function showMainTab(tab) {
            document.getElementById('panel-users').style.display = tab === 'users' ? 'block' : 'none';
            document.getElementById('panel-projects').style.display = tab === 'projects' ? 'block' : 'none';
            document.getElementById('mainTabUsers').classList.toggle('active', tab === 'users');
            document.getElementById('mainTabProjects').classList.toggle('active', tab === 'projects');

            // Keep URL in sync
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            history.replaceState({}, '', url);

            // Init project view when switching to it for the first time
            if (tab === 'projects') {
                renderView(appState.currentView);
            }
        }

        // ── Bootstrap: if starting on Projects tab, render it ────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($active_tab === 'projects'): ?>
                renderView(appState.currentView);
            <?php endif; ?>

            // Update progress bars in details modal dynamically
            document.addEventListener('_detailsOpened', updateProgressBars);
        });

        // ── Enhance openDetails to also populate progress bar spans ──────────────────
        const _origOpenDetails = openDetails;
        function openDetails(projectId) {
            _origOpenDetails(projectId);
            const proj = appState.projects.find(p => p.id === projectId);
            if (!proj) return;
            const pct = proj.budget > 0 ? Math.round((proj.paid / proj.budget) * 100) : 0;
            const owedAmt = proj.budget - proj.paid;
            const owedPct = 100 - pct;
            document.getElementById('ps-total').textContent = `€${proj.budget.toLocaleString()}`;
            document.getElementById('ps-paid').textContent = `€${proj.paid.toLocaleString()}`;
            document.getElementById('ps-owed').textContent = `€${owedAmt.toLocaleString()}`;
            document.getElementById('ps-paid-pct').textContent = `${pct}% του συνολικού`;
            document.getElementById('ps-owed-pct').textContent = `${owedPct}% του συνολικού`;
            document.getElementById('bar-paid').style.width = `${pct}%`;
            document.getElementById('bar-owed').style.width = `${owedPct}%`;
        }

        // ── Enhance openReport to also populate report KPI cards ─────────────────────
        const _origOpenReport = openReport;
        function openReport() {
            _origOpenReport();
            const proj = appState.projects.find(p => p.id === currentProjectId);
            if (!proj) return;
            const totalCost = proj.costLabor + proj.costMaterials;
            const profit = proj.budget - totalCost;
            const pct = proj.budget > 0 ? ((profit / proj.budget) * 100).toFixed(1) : 0;
            document.getElementById('rep-budget').textContent = `€${proj.budget.toLocaleString()}`;
            document.getElementById('rep-cost').textContent = `€${totalCost.toLocaleString()}`;
            document.getElementById('rep-profit').textContent = `${profit >= 0 ? '+' : ''}€${profit.toLocaleString()}`;
            document.getElementById('rep-pct').textContent = `${profit >= 0 ? '+' : ''}${pct}%`;

            // Staff table
            const tbody = document.getElementById('staffTableBody');
            tbody.innerHTML = appState.employees
                .filter(e => e.hours > 0)
                .map(e => `<tr>
            <td>${e.name}</td>
            <td>${e.role}</td>
            <td>${e.hours}h</td>
            <td>€${(e.rate * e.hours).toFixed(2)}</td>
        </tr>`).join('') || '<tr><td colspan="4" style="text-align:center;color:#94a3b8;">Δεν υπάρχουν εγγραφές ωρών.</td></tr>';
        }
    </script>

</body>

</html>