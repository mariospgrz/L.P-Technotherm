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
$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'users') ? 'users' : 'projects';

// ── Fetch all users for User Management tab ───────────────────────────────────
$users = [];
$res = $conn->query(
    "SELECT u.id, u.username, u.name, u.role, u.email, u.`Phone number` AS phone, u.hourly_rate, u.created_at,
            (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, te.clock_in, te.clock_out) / 60.0), 0) FROM time_entries te WHERE te.user_id = u.id AND te.clock_out IS NOT NULL) AS normal_hours,
            (SELECT COALESCE(SUM(orq.hours), 0) FROM overtime_requests orq WHERE orq.user_id = u.id AND orq.status = 'approved') AS overtime_hours
       FROM users u
      ORDER BY u.created_at DESC"
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
    'hours' => (float) ($u['normal_hours'] ?? 0),
    'overtime' => (float) ($u['overtime_hours'] ?? 0)
], $users));

// ── Fetch all projects for Projects tab ───────────────────────────────────────
$projects = [];
$proj_res = $conn->query(
    "SELECT p.id, p.name, p.status, p.location, p.start_date, p.completed_at, p.budget,
        (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, te.clock_in, te.clock_out) / 60.0 * u.hourly_rate), 0) 
         FROM time_entries te JOIN users u ON te.user_id = u.id 
         WHERE te.project_id = p.id AND te.clock_out IS NOT NULL) 
        + (SELECT COALESCE(SUM(o.hours * u2.hourly_rate), 0)
         FROM overtime_requests o JOIN users u2 ON o.user_id = u2.id
         WHERE o.project_id = p.id AND o.status = 'approved') AS costLabor,
        (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE project_id = p.id) AS costMaterials,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE project_id = p.id) AS paid
     FROM projects p
     ORDER BY p.start_date DESC"
);
if ($proj_res) {
    while ($row = $proj_res->fetch_assoc()) {
        $row['date'] = date('d/m/Y', strtotime($row['start_date']));
        $row['completedAt'] = $row['completed_at'] ? date('d/m/Y', strtotime($row['completed_at'])) : null;
        $row['budget'] = (float) $row['budget'];
        $row['costLabor'] = (float) $row['costLabor'];
        $row['costMaterials'] = (float) $row['costMaterials'];
        $row['paid'] = (float) $row['paid'];
        $row['id'] = (int) $row['id'];
        $projects[] = $row;
    }
}
$projects_json = json_encode($projects);

// ── Fetch all overtime requests for Overtime tab ───────────────────────────────
$overtime_requests = [];
$ot_res = $conn->query(
    "SELECT o.id, u.name, o.hours, o.date, o.status,
            p.name AS project,
            COALESCE(o.description, '') AS reason,
            DATE_FORMAT(o.created_at, '%d/%m/%Y %H:%i') AS submitted
       FROM overtime_requests o
       JOIN users u    ON o.user_id    = u.id
       JOIN projects p ON o.project_id = p.id
      ORDER BY o.created_at DESC"
);
if ($ot_res) {
    while ($row = $ot_res->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['hours'] = (float) $row['hours'];
        $overtime_requests[] = $row;
    }
}
$overtime_json = json_encode($overtime_requests, JSON_UNESCAPED_UNICODE);

// ── Fetch all invoices for Invoices tab ───────────────────────────────────────
$all_invoices = [];
$inv_res = $conn->query(
    "SELECT i.id, i.description AS vendor, i.amount, i.date, i.photo_url,
            p.name AS project
       FROM invoices i
       JOIN projects p ON i.project_id = p.id
      ORDER BY i.date DESC, i.created_at DESC"
);
if ($inv_res) {
    while ($row = $inv_res->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['amount'] = (float) $row['amount'];
        $all_invoices[] = $row;
    }
}
$invoices_json = json_encode($all_invoices, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Διαχειριστή | LP Technotherm</title>
    <meta name="description" content="Πίνακας ελέγχου διαχειριστή - LP Technotherm">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Admin CSS -->
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="../frontend/CSS/logout_button.css">
    <link rel="stylesheet" href="CSS/responsive.css">
    <link rel="icon" type="image/jpeg" href="../frontend/images/images.jpg">
    <style>
        /* Modern Report Grid Styles */
        .report-cards-row {
            display: grid;
            gap: 15px;
            width: 100%;
            margin-bottom: 20px;
        }

        .report-cards-6 {
            grid-template-columns: repeat(3, 1fr);
        }

        .report-cards-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .report-cards-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .report-cards-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .report-cards-1 {
            grid-template-columns: 1fr;
        }

        .report-info-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .report-card-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .report-card-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Payroll Archive Tabs/Accordion */
        .archive-controls {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }

        .archive-year-row {
            margin-bottom: 10px;
        }

        .archive-year-row:last-child {
            margin-bottom: 0;
        }

        .year-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: #f8fafc;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }

        .year-header:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .year-header.active {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: var(--primary);
        }

        .year-header strong {
            font-size: 1rem;
        }

        .year-header i {
            transition: transform 0.3s;
            color: #94a3b8;
        }

        .year-header.active i {
            transform: rotate(180deg);
            color: var(--primary);
        }

        .month-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
            padding: 15px 10px 5px 10px;
            display: none;
        }

        .month-grid.active {
            display: grid;
        }

        .month-pill {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
            color: #475569;
            font-weight: 500;
        }

        .month-pill:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: var(--text-main);
        }

        .month-pill.selected {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .archive-empty {
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .archive-stats-dropdown {
            grid-column: 1 / -1;
            width: 100%;
            margin: 10px 0 20px 0;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="app-container">

        <!-- ========== HEADER ========== -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-container">
                    <img src="../frontend/images/images.jpg" alt="LP Technotherm"
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

            <div class="users-action-bar" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-panel btn-panel-primary" style="width: auto; padding: 10px 20px; font-weight: 500;"
                    onclick="toggleModal('modalCreateUser')">
                    <i class="fas fa-user-plus"></i> Νέος Χρήστης
                </button>
                <button class="btn-panel"
                    style="width: auto; padding: 10px 20px; font-weight: 500; background: var(--warning); color: #fff; border: none; cursor: pointer;"
                    onclick="toggleModal('modalEditUser')">
                    <i class="fas fa-user-edit"></i> Επεξεργασία Χρήστη
                </button>
                <button class="btn-panel btn-panel-danger" style="width: auto; padding: 10px 20px; font-weight: 500;"
                    onclick="toggleModal('modalDeleteUser')">
                    <i class="fas fa-user-minus"></i> Διαγραφή Χρήστη
                </button>
            </div>

            <!-- USERS TABLE -->
            <div class="section-heading"
                style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div>
                    <i class="fas fa-list-ul"></i> Κατάλογος Χρηστών
                    <span class="count-pill" id="usersCountPill">
                        <?= count($users) ?> χρήστες
                    </span>
                </div>
                <!-- USERS TABLE CONTROLS -->
                <div class="users-table-controls" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="search-container" style="flex:1; position:relative;">
                        <i class="fas fa-search"
                            style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#6b7280; pointer-events:none;"></i>
                        <input type="text" id="userSearchInput" placeholder="Αναζήτηση..."
                            style="padding:8px 8px 8px 35px !important; border-radius:6px; border:1px solid var(--border-color); width:100%; box-sizing:border-box; font-family:inherit;">
                    </div>
                    <select id="userRoleFilter"
                        style="padding:8px; border-radius:6px; border:1px solid var(--border-color); background:#fff; min-width:140px;">
                        <option value="">Όλοι οι Ρόλοι</option>
                        <option value="administrator">Διαχειριστής</option>
                        <option value="supervisor">Υπεύθυνος</option>
                        <option value="helper">Βοηθός</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x">
                <?php if (empty($users)): ?>
                    <div class="no-data"><i class="fas fa-users-slash"></i> Δεν βρέθηκαν χρήστες.</div>
                <?php else: ?>
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th style="cursor:pointer;" data-sort-type="number" data-col="0"># <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="string" data-col="1">Ονοματεπώνυμο <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="string" data-col="2">Username <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="string" data-col="3">Ρόλος <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="string" data-col="4">Email <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="string" data-col="5">Τηλέφωνο <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="currency" data-col="6">Ωρομίσθιο <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
                                <th style="cursor:pointer;" data-sort-type="date" data-col="7">Εγγραφή <i
                                        class="fas fa-sort float-right" style="color:#9ca3af;"></i></th>
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
                </div>
            </div>

            <!-- PROJECT SUB-TABS -->
            <nav class="tabs-nav">
                <button class="tab-link active" onclick="switchView('active-projects')">Ενεργά Έργα</button>
                <button class="tab-link" onclick="switchView('completed-projects')">Ολοκληρωμένα Έργα</button>
                <button class="tab-link" onclick="switchView('invoices')">Τιμολόγια</button>
                <button class="tab-link" onclick="switchView('employees')">Υπάλληλοι (
                    <?= count(array_filter($users, fn($u) => in_array($u['role'], ['supervisor', 'helper']))) ?>)
                </button>
                <button class="tab-link" onclick="switchView('overtime')">Αιτήσεις Επιπλέον Ωρών</button>
            </nav>

            <main id="mainContent"></main>

        </div><!-- /panel-projects -->


        <!-- ========== MODALS ========== -->

        <!-- Modal: Δημιουργία Χρήστη -->
        <div id="modalCreateUser" class="modal-overlay">
            <div class="modal-container" style="max-width:480px;">
                <div class="modal-header">
                    <h3><i class="fas fa-user-plus icon-primary"></i> Δημιουργία Νέου Χρήστη</h3>
                    <button class="close-modal" aria-label="Close"
                        onclick="toggleModal('modalCreateUser')">&times;</button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <form class="panel-form" action="../Backend/CreateUser/create_user.php" method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cu_username">Username *</label>
                                <input type="text" id="cu_username" name="username" placeholder="π.χ. nikos123" required
                                    autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="cu_password">Κωδικός * (min. 8)</label>
                                <input type="password" id="cu_password" name="password"
                                    placeholder="Τουλάχιστον 8 χαρακτήρες" minlength="6" required>
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
                        <button type="submit" class="btn btn-blue" style="width:100%; margin-top:10px;">
                            <i class="fas fa-plus"></i> Δημιουργία Χρήστη
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Επεξεργασία Χρήστη -->
        <div id="modalEditUser" class="modal-overlay">
            <div class="modal-container" style="max-width:480px;">
                <div class="modal-header">
                    <h3><i class="fas fa-user-edit icon-primary" style="color:var(--warning);"></i> Επεξεργασία Χρήστη
                    </h3>
                    <button class="close-modal" aria-label="Close"
                        onclick="toggleModal('modalEditUser')">&times;</button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <form class="panel-form" action="../Backend/EditUser/edit_user.php" method="POST">
                        <div class="form-group">
                            <label for="eu_user_select">Επιλογή Χρήστη *</label>
                            <select id="eu_user_select" name="user_id" required onchange="populateEditForm(this.value)">
                                <option value="" disabled selected>Επιλέξτε χρήστη…</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>">
                                        <?= htmlspecialchars(($u['name'] ?: $u['username']) . ' (@' . $u['username'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="eu_full_name">Ονοματεπώνυμο *</label>
                                <input type="text" id="eu_full_name" name="full_name" required>
                            </div>
                            <div class="form-group">
                                <label for="eu_role">Ρόλος *</label>
                                <select id="eu_role" name="role" required>
                                    <option value="administrator">Διαχειριστής</option>
                                    <option value="supervisor">Υπεύθυνος</option>
                                    <option value="helper">Βοηθός</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="eu_phone">Τηλέφωνο *</label>
                                <input type="text" id="eu_phone" name="phone" required>
                            </div>
                            <div class="form-group">
                                <label for="eu_email">Email *</label>
                                <input type="email" id="eu_email" name="email" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="eu_hourly_rate">Ωρομίσθιο (€)</label>
                            <input type="number" id="eu_hourly_rate" name="hourly_rate" min="0" step="0.01">
                        </div>
                        <button type="submit" class="btn"
                            style="width:100%; margin-top:10px; background:var(--warning); color:#fff; border:none;">
                            <i class="fas fa-save"></i> Αποθήκευση Αλλαγών
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Διαγραφή Χρήστη -->
        <div id="modalDeleteUser" class="modal-overlay">
            <div class="modal-container" style="max-width:480px;">
                <div class="modal-header">
                    <h3><i class="fas fa-user-minus" style="color:var(--danger); margin-right:8px;"></i> Διαγραφή Χρήστη
                    </h3>
                    <button class="close-modal" aria-label="Close"
                        onclick="toggleModal('modalDeleteUser')">&times;</button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <form class="panel-form" action="../Backend/DeleteUser/delete_user.php" method="POST">
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
                        <div class="confirm-row" style="margin-bottom:15px; margin-top:15px;">
                            <input type="checkbox" id="du_confirm" name="confirm" required
                                style="width:auto; margin-right:8px;" onchange="
                                const btn = document.getElementById('btnDeleteSubmit');
                                if (this.checked) {
                                    btn.disabled = false;
                                    btn.style.backgroundColor = 'var(--danger)';
                                    btn.style.color = '#ffffff';
                                    btn.style.cursor = 'pointer';
                                    btn.style.opacity = '1';
                                } else {
                                    btn.disabled = true;
                                    btn.style.backgroundColor = '#e5e7eb';
                                    btn.style.color = '#9ca3af';
                                    btn.style.cursor = 'not-allowed';
                                    btn.style.opacity = '0.7';
                                }
                            ">
                            <label for="du_confirm" style="display:inline; font-weight:normal; cursor:pointer;">
                                Επιβεβαιώνω ότι θέλω να διαγράψω <strong>οριστικά</strong> αυτόν τον χρήστη.
                            </label>
                        </div>
                        <button type="submit" id="btnDeleteSubmit" class="btn"
                            style="width:100%; border:none; background-color:#e5e7eb; color:#9ca3af; cursor:not-allowed; opacity:0.7; transition:all 0.3s ease;"
                            disabled>
                            <i class="fas fa-trash-alt"></i> Οριστική Διαγραφή
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: Νέο Έργο -->
        <div id="projectModal" class="modal-overlay">
            <div class="modal-container" style="max-width:480px;">
                <div class="modal-header">
                    <h3><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>Δημιουργία Νέου
                        Έργου</h3>
                    <button class="close-modal" onclick="toggleModal('projectModal')" title="Κλείσιμο">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="projectFormError"
                        style="display:none;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;border-radius:8px;padding:10px 14px;font-size:0.85rem;margin-bottom:14px;">
                        <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>
                        <span id="projectFormErrorMsg"></span>
                    </div>
                    <form id="createProjectForm" action="../Backend/CreateProject/create_project.php" method="POST"
                        onsubmit="return validateProjectForm()" novalidate>
                        <div class="panel-form" style="margin-bottom:0;">
                            <div class="form-group">
                                <label for="proj_name">Όνομα Έργου <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="proj_name" name="project_name"
                                    placeholder="π.χ. Εγκατάσταση Κλιματισμού - Hotel Αθήνα" maxlength="200" required
                                    autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="proj_location">Τοποθεσία <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="proj_location" name="location" placeholder="π.χ. Αθήνα, Κέντρο"
                                    maxlength="200" required autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="proj_budget">Προϋπολογισμός (€) <span
                                        style="color:var(--danger);">*</span></label>
                                <input type="number" id="proj_budget" name="budget" placeholder="π.χ. 45000" min="0.01"
                                    step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label for="proj_start_date">Ημερομηνία Έναρξης <span
                                        style="color:var(--danger);">*</span></label>
                                <input type="date" id="proj_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;margin-top:6px;">
                            <button type="button" class="btn" onclick="toggleModal('projectModal')"
                                style="flex:1;background:#f3f4f6;color:#374151;border:1px solid var(--border-color);">
                                <i class="fas fa-times"></i> Ακύρωση
                            </button>
                            <button type="submit" class="btn btn-blue" style="flex:2;">
                                <i class="fas fa-plus"></i> Δημιουργία
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>



        <!-- Modal: Αναφορά Έργου -->
        <div id="reportModal" class="modal-overlay">
            <div class="modal-container report-container" style="max-width:960px; max-height:92vh; overflow-y:auto;">
                <div class="modal-header"
                    style="position:sticky; top:0; background:#fff; z-index:10; border-bottom:1px solid var(--border-color); padding:18px 24px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h2 style="font-size:1.2rem; margin-bottom:2px;">Αναφορά Έργου</h2>
                        <p id="reportProjName" style="color:var(--text-muted); font-size:0.9rem;"></p>
                    </div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <button class="btn btn-blue" onclick="exportReportPDF()"
                            style="font-size:0.82rem; padding:7px 14px;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn" onclick="exportReportExcel()"
                            style="font-size:0.82rem; padding:7px 14px; background:#10b981; color:#fff; border:none;">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="close-modal" onclick="closeReport()" style="margin-left:4px;">&times;</button>
                    </div>
                </div>
                <div class="modal-body" id="reportPrintArea" style="padding:20px 24px;">
                    <!-- Loading -->
                    <div id="reportLoading" style="text-align:center; padding:40px; color:var(--text-muted);">
                        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;"></i>
                        <p style="margin-top:10px;">Φόρτωση αναφοράς...</p>
                    </div>
                    <!-- Content (hidden until loaded) -->
                    <div id="reportContent" style="display:none;">
                        <!-- Official Report Header -->
                        <div class="report-official-header">
                            <div class="roh-left">
                                <img src="../frontend/images/images.jpg" alt="LP Technotherm" class="roh-logo">
                                <div class="roh-company">
                                    <h3>LP TECHNOTHERM</h3>
                                    <p>Συστήματα Κλιματισμού & Θέρμανσης</p>
                                </div>
                            </div>
                            <div class="roh-right">
                                <div class="roh-info-item">
                                    <span class="roh-label">ΕΡΓΟ:</span>
                                    <strong id="roh-proj-name" class="roh-value"></strong>
                                </div>
                                <div class="roh-info-item">
                                    <span class="roh-label">ΤΟΠΟΘΕΣΙΑ:</span>
                                    <span id="roh-proj-loc" class="roh-value"></span>
                                </div>
                                <div class="roh-info-item">
                                    <span class="roh-label">ΔΙΑΡΚΕΙΑ:</span>
                                    <span id="roh-proj-dates" class="roh-value"></span>
                                </div>
                                <div class="roh-info-item">
                                    <span class="roh-label">ΚΑΤΑΣΤΑΣΗ:</span>
                                    <span id="roh-proj-status" class="roh-value"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Οικονομική Επισκόπηση -->
                        <div class="report-section">
                            <h4 class="report-section-title">Οικονομική Επισκόπηση</h4>
                            <div class="report-cards-row report-cards-6">
                                <div class="report-info-card"><span
                                        class="report-card-label">Προϋπολογισμός</span><strong class="report-card-value"
                                        id="rep-budget"></strong></div>
                                <div class="report-info-card"><span class="report-card-label">Εργατικά</span><strong
                                        class="report-card-value" id="reportLaborCost"></strong></div>
                                <div class="report-info-card"><span class="report-card-label">Υλικά</span><strong
                                        class="report-card-value" id="reportMaterialCost"></strong></div>
                                <div class="report-info-card"><span class="report-card-label">Συνολικό
                                        Κόστος</span><strong class="report-card-value" id="reportTotalCost"></strong>
                                </div>
                                <div class="report-info-card"><span class="report-card-label">Κέρδος/Ζημία</span><strong
                                        class="report-card-value" id="reportProfit"></strong></div>
                                <div class="report-info-card"><span class="report-card-label">Ποσοστό (%)</span><strong
                                        class="report-card-value" id="rep-pct"></strong></div>
                            </div>
                        </div>

                        <div class="report-section">
                            <div class="report-cards-row report-cards-1">
                                <div class="chart-box" style="margin-bottom: 20px;">
                                    <h4 style="font-size: 0.9rem; margin-bottom: 10px; color: var(--text-muted);">
                                        ΚΑΤΑΝΟΜΗ ΚΟΣΤΟΥΣ (%)</h4>
                                    <div style="position:relative; height:220px;"><canvas id="costPieChart"></canvas>
                                    </div>
                                </div>
                                <div class="chart-box">
                                    <h4 style="font-size: 0.9rem; margin-bottom: 10px; color: var(--text-muted);">ΩΡΕΣ
                                        ΑΝΑ ΑΤΟΜΟ</h4>
                                    <div id="laborChartContainer" style="position:relative; min-height:200px;"><canvas
                                            id="laborBarChart"></canvas></div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Πληρωμές -->
                        <div class="report-section">
                            <h4 class="report-section-title">Πληρωμές Πελάτη</h4>
                            <div class="report-cards-row report-cards-3" id="reportPaymentSummary"></div>
                            <div class="report-table-wrap">
                                <table class="data-table" id="reportPaymentsTable">
                                    <thead>
                                        <tr>
                                            <th>Ημερομηνία</th>
                                            <th>Ποσό</th>
                                            <th>Σημείωση</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reportPaymentsBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Section: Προσωπικό -->
                        <div class="report-section">
                            <h4 class="report-section-title">Ομάδα & Εργατοώρες</h4>
                            <div class="report-table-wrap">
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

                        <!-- Section: Υπερωρίες -->
                        <div id="reportOvertimeSection" class="report-section" style="display:none;">
                            <h4 class="report-section-title">Εγκεκριμένες Υπερωρίες</h4>
                            <div class="report-table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Όνομα</th>
                                            <th>Ημερομηνία</th>
                                            <th>Ώρες</th>
                                            <th>Περιγραφή</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reportOvertimeBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Section: Τιμολόγια -->
                        <div class="report-section">
                            <h4 class="report-section-title">Τιμολόγια Υλικών</h4>
                            <div class="report-table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Ημερομηνία</th>
                                            <th>Προμηθευτής</th>
                                            <th>Ποσό</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reportInvoicesBody"></tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Επιβεβαίωση Ενέργειας -->
        <div id="confirmModal" class="modal-overlay">
            <div class="modal-container"
                style="max-width:400px; transform: translateY(20px); transition: transform 0.3s ease-out;">
                <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                    <h3 id="confirmModalTitle" style="font-size: 1.15rem; color: var(--text-main);">Επιβεβαίωση</h3>
                    <button class="close-modal" onclick="toggleModal('confirmModal')">&times;</button>
                </div>
                <div class="modal-body" style="padding: 20px 24px 28px; text-align: center;">
                    <div class="confirm-icon"
                        style="width: 56px; height: 56px; background: #fff7ed; color: #f97316; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-size: 1.5rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p id="confirmModalMessage"
                        style="margin-bottom: 28px; font-size: 0.95rem; color: var(--text-muted); line-height: 1.6;">
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button class="btn" onclick="toggleModal('confirmModal')"
                            style="flex: 1; background: #fff; color: #374151; border: 1px solid #d1d5db; justify-content: center;">
                            Ακύρωση
                        </button>
                        <button id="confirmModalActionBtn" class="btn btn-blue"
                            style="flex: 1; justify-content: center; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);">
                            Επιβεβαίωση
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /app-container -->

    <!-- ── PHP data injection: must be inline because it uses PHP values ── -->
    <script>
        // Passes real DB users to admin.js so the Employees tab shows live data
        window.__DB_EMPLOYEES__ = <?= $employees_json ?>;
        // Also pass full user DB array for editing populator
        window.__DB_USERS_FULL__ = <?= json_encode($users, JSON_UNESCAPED_UNICODE) ?>;
        // Passes real DB projects to admin.js
        window.__DB_PROJECTS__ = <?= $projects_json ?>;
        // Passes real DB overtime requests to admin.js
        window.__DB_OVERTIME__ = <?= $overtime_json ?>;
        // Passes real DB invoices to admin.js
        window.__DB_INVOICES__ = <?= $invoices_json ?>;
        <?php if ($active_tab === 'projects'): ?>
            // Start on projects tab — trigger first render after admin.js loads
            document.addEventListener('DOMContentLoaded', function () {
                renderView(appState.currentView);
            });
        <?php endif; ?>
    </script>

    <!-- SheetJS for Excel export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <!-- Print styles for PDF export -->
    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            /* Hide everything */
            body,
            body * {
                visibility: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Show only report content */
            #reportModal,
            #reportModal *,
            #reportPrintArea,
            #reportPrintArea *,
            #reportContent,
            #reportContent * {
                visibility: visible !important;
            }

            /* Reset modal to fill page */
            #reportModal {
                position: absolute !important;
                inset: 0 !important;
                background: #fff !important;
                display: block !important;
                overflow: visible !important;
                width: 100% !important;
            }

            #reportModal .modal-overlay {
                background: none !important;
            }

            #reportModal .modal-container {
                position: relative !important;
                width: 100% !important;
                max-width: 100% !important;
                max-height: none !important;
                overflow: visible !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            #reportModal .modal-header {
                display: none !important;
            }

            #reportModal .modal-body {
                padding: 5mm 0 !important;
                width: 100% !important;
            }

            #reportContent {
                width: 100% !important;
            }

            /* Grids - scale down for A4 */
            .report-cards-row {
                display: grid !important;
                gap: 8px !important;
                width: 100% !important;
                margin-bottom: 15px !important;
            }

            .report-cards-6 {
                grid-template-columns: repeat(3, 1fr) !important;
            }

            .report-cards-4 {
                grid-template-columns: repeat(4, 1fr) !important;
            }

            .report-cards-3 {
                grid-template-columns: repeat(3, 1fr) !important;
            }

            .report-cards-2 {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .report-info-card {
                background: #f9fafb !important;
                border: 1px solid #ddd !important;
                padding: 6px 8px !important;
            }

            .report-card-label {
                font-size: 7pt !important;
            }

            .report-card-value {
                font-size: 9pt !important;
            }

            /* Page break rules — keep sections together */
            .report-section {
                margin-bottom: 25px !important;
                page-break-inside: auto;
                break-inside: auto;
            }

            .report-section-title {
                font-size: 10pt !important;
                margin-bottom: 8px !important;
                padding: 0 0 0 8px !important;
                border-left: 3pt solid #2563eb !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5pt !important;
                page-break-after: avoid;
                break-after: avoid;
            }

            /* Charts — separate each chart and keep as unit */
            .chart-box {
                padding: 10px 0 !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            #reportModal canvas {
                width: 100% !important;
                height: auto !important;
            }

            /* Tables — full width, compact, smart page breaks */
            .report-table-wrap {
                overflow: visible !important;
                width: 100% !important;
            }

            .data-table {
                width: 100% !important;
                font-size: 8pt !important;
                page-break-inside: auto;
            }

            .data-table thead {
                display: table-header-group;
            }

            .data-table th {
                padding: 4px 6px !important;
                font-size: 7.5pt !important;
            }

            .data-table td {
                padding: 4px 6px !important;
            }

            .data-table tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .data-table tfoot {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Cards grids — keep together */
            .report-cards-row {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* KPI cards in finance section */
            .kpi-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 6px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .kpi-card {
                padding: 8px 10px !important;
            }

            .kpi-card span {
                font-size: 7pt !important;
            }

            .kpi-card h3 {
                font-size: 10pt !important;
            }

            /* Official Header Print */
            .report-official-header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                border-bottom: 2pt solid #2563eb !important;
                padding-bottom: 10pt !important;
                margin-bottom: 15pt !important;
            }

            .roh-logo {
                height: 45pt !important;
            }

            .roh-company h3 {
                font-size: 14pt !important;
                color: #2563eb !important;
                margin: 0 !important;
            }

            .roh-company p {
                font-size: 8pt !important;
                margin: 0 !important;
            }

            .roh-info-item {
                margin-bottom: 2pt !important;
            }

            .roh-label {
                font-size: 7pt !important;
                width: 60pt !important;
                display: inline-block !important;
            }

            .roh-value {
                font-size: 9pt !important;
            }

            /* Hide non-report UI */
            .main-header,
            .main-tab-bar,
            .flash-banner,
            #panel-users,
            #panel-projects,
            #confirmModal,
            #projectModal,
            #modalCreateUser,
            #modalEditUser,
            #modalDeleteUser {
                display: none !important;
            }
        }

        /* Section Titles Style */
        .report-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 15px;
            padding-left: 12px;
            border-left: 4px solid var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
        }

        /* Official Header Desktop */
        .report-official-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .roh-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .roh-logo {
            height: 60px;
            object-fit: contain;
        }

        .roh-company h3 {
            margin: 0;
            color: var(--primary);
            font-size: 1.4rem;
            letter-spacing: 0.5px;
        }

        .roh-company p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .roh-right {
            text-align: right;
        }

        .roh-info-item {
            margin-bottom: 4px;
            display: flex;
            justify-content: flex-end;
            align-items: baseline;
            gap: 8px;
        }

        .roh-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            opacity: 0.8;
        }

        .roh-value {
            font-size: 0.95rem;
            color: var(--text-main);
            font-weight: 600;
        }
    </style>
    <!-- Admin JS -->
    <script src="JS/admin.js"></script>

</body>

</html>