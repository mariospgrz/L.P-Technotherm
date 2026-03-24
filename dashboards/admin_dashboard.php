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

// ── Fetch all projects for Projects tab ───────────────────────────────────────
$projects = [];
$proj_res = $conn->query(
    "SELECT p.id, p.name, p.status, p.location, p.start_date, p.budget,
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
        $row['id']    = (int) $row['id'];
        $row['hours'] = (float) $row['hours'];
        $overtime_requests[] = $row;
    }
}
$overtime_json = json_encode($overtime_requests, JSON_UNESCAPED_UNICODE);
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
    <!-- Admin CSS -->
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="/frontend/CSS/logout_button.css">
    <link rel="stylesheet" href="CSS/responsive.css">
  <link rel="icon" type="image/jpeg" href="/frontend/images/images.jpg">
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
                                <i class="fas fa-exclamation-triangle"></i>
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
                    <form id="createProjectForm" action="/Backend/CreateProject/create_project.php" method="POST"
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

    <!-- ── PHP data injection: must be inline because it uses PHP values ── -->
    <script>
        // Passes real DB users to admin.js so the Employees tab shows live data
        window.__DB_EMPLOYEES__ = <?= $employees_json ?>;
        // Passes real DB projects to admin.js
        window.__DB_PROJECTS__ = <?= $projects_json ?>;
        // Passes real DB overtime requests to admin.js
        window.__DB_OVERTIME__ = <?= $overtime_json ?>;
        <?php if ($active_tab === 'projects'): ?>
            // Start on projects tab — trigger first render after admin.js loads
            document.addEventListener('DOMContentLoaded', function () {
                renderView(appState.currentView);
            });
        <?php endif; ?>
    </script>

    <!-- Admin JS -->
    <script src="JS/admin.js"></script>

</body>

</html>