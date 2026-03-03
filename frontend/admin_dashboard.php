<?php
/**
 * frontend/admin_dashboard.php
 * Admin Dashboard – requires administrator session via admin_session.php
 */
require_once __DIR__ . '/../Backend/admin_session.php';
require_once __DIR__ . '/../Backend/Database/Database.php';

// ── Read flash messages from URL ─────────────────────────────────────────────
$flash_success = isset($_GET['success']) ? htmlspecialchars(urldecode($_GET['success'])) : '';
$flash_error = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error'])) : '';
$active_tab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'users';

// ── Fetch all users for the Users tab ────────────────────────────────────────
$users = [];
$result = $conn->query('SELECT id, username, name, role, email, `Phone number` AS phone, hourly_rate, created_at FROM users ORDER BY created_at DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$logged_in_username = $_SESSION['username'];
$logged_in_id = $_SESSION['user_id'];

$role_labels = [
    'administrator' => 'Διαχειριστής',
    'supervisor' => 'Υπεύθυνος',
    'helper' => 'Βοηθός',
];
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Διαχειριστή | LP Technotherm</title>
    <meta name="description" content="Πίνακας ελέγχου διαχειριστή - LP Technotherm">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Dashboard CSS -->
    <link rel="stylesheet" href="admin dashboard.css">
    <style>
        /* ───── Extra styles for PHP admin dashboard ───── */
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* Flash alerts */
        .flash-banner {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 0.93rem;
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

        /* User management panel */
        .users-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        @media (max-width: 900px) {
            .users-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 24px;
        }

        .panel-box h3 {
            margin: 0 0 18px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-box h3 i {
            color: var(--primary);
        }

        /* Form inside panel */
        .panel-form .form-group {
            margin-bottom: 14px;
        }

        .panel-form label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .panel-form input,
        .panel-form select {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            background: #f8fafc;
            transition: border-color 0.2s;
            box-sizing: border-box;
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
            gap: 12px;
        }

        /* Delete danger zone */
        .danger-box {
            border-color: #fca5a5;
            background: #fff8f8;
        }

        .danger-box h3 i {
            color: var(--danger);
        }

        .confirm-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 14px;
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

        /* Users table */
        .users-table-wrap {
            overflow-x: auto;
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

        .data-table tbody tr:hover {
            background: #f8faff;
        }

        /* Section heading */
        .section-heading {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 28px 0 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-heading i {
            color: var(--primary);
        }

        /* Logout / header tweaks */
        .header-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .header-meta strong {
            color: var(--text-main);
        }

        /* btn submit variants */
        .btn-submit-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: opacity 0.2s;
            width: 100%;
            justify-content: center;
        }

        .btn-submit-danger:hover {
            opacity: 0.9;
        }

        .btn-submit-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: opacity 0.2s;
            width: 100%;
            justify-content: center;
        }

        .btn-submit-primary:hover {
            opacity: 0.9;
        }

        .no-users {
            text-align: center;
            color: var(--text-muted);
            padding: 30px;
            font-size: 0.95rem;
        }
    </style>
</head>

<body>
    <div class="app-container">

        <!-- ========== HEADER ========== -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-container">
                    <img src="images/logo.png" alt="LP Technotherm Logo"
                        onerror="this.src='https://via.placeholder.com/150x50?text=LP+Technotherm'"
                        class="company-logo">
                </div>
                <div class="user-info">
                    <h1>Πίνακας Ελέγχου Διαχειριστή</h1>
                    <p>Καλώς ήρθατε, <strong>
                            <?= $logged_in_username ?>
                        </strong>
                        &nbsp;·&nbsp;<span class="header-meta">Ρόλος: <strong>Διαχειριστής</strong></span></p>
                </div>
            </div>
            <div class="header-right">
                <a href="/L.P-Technotherm/Backend/logout.php" class="logout-link" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i> Αποσύνδεση
                </a>
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

        <!-- ========== TAB NAVIGATION ========== -->
        <nav class="tabs-nav" id="mainTabs">
            <button class="tab-link <?= $active_tab === 'users' ? 'active' : '' ?>" onclick="switchTab('users')"
                id="tab-users">
                <i class="fas fa-users"></i> Διαχείριση Χρηστών
            </button>
            <button class="tab-link <?= $active_tab === 'dashboard' ? 'active' : '' ?>" onclick="switchTab('dashboard')"
                id="tab-dashboard">
                <i class="fas fa-chart-bar"></i> Επισκόπηση Έργων
            </button>
        </nav>

        <!-- ========== TAB: USER MANAGEMENT ========== -->
        <div id="view-users" class="tab-view" style="display:<?= $active_tab === 'users' ? 'block' : 'none' ?>;">

            <div class="users-grid">

                <!-- CREATE USER FORM -->
                <div class="panel-box">
                    <h3><i class="fas fa-user-plus"></i> Δημιουργία Νέου Χρήστη</h3>
                    <form class="panel-form" action="/L.P-Technotherm/Backend/CreateUser/create_user.php" method="POST"
                        id="createUserForm">
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
                        <button type="submit" class="btn-submit-primary" id="createUserBtn">
                            <i class="fas fa-plus"></i> Δημιουργία Χρήστη
                        </button>
                    </form>
                </div>

                <!-- DELETE USER FORM -->
                <div class="panel-box danger-box">
                    <h3><i class="fas fa-user-minus"></i> Διαγραφή Χρήστη</h3>
                    <form class="panel-form" action="/L.P-Technotherm/Backend/DeleteUser/delete_user.php" method="POST"
                        id="deleteUserForm">
                        <div class="form-group">
                            <label for="du_username">Username χρήστη προς διαγραφή *</label>
                            <select id="du_username" name="username" required>
                                <option value="" disabled selected>Επιλέξτε χρήστη...</option>
                                <?php foreach ($users as $u): ?>
                                    <?php if ((int) $u['id'] !== (int) $logged_in_id): ?>
                                        <option value="<?= htmlspecialchars($u['username']) ?>">
                                            <?= htmlspecialchars($u['name'] . ' (' . $u['username'] . ')') ?>
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
                        <button type="submit" class="btn-submit-danger" id="deleteUserBtn">
                            <i class="fas fa-trash-alt"></i> Οριστική Διαγραφή
                        </button>
                    </form>
                </div>

            </div><!-- /users-grid -->

            <!-- USERS TABLE -->
            <div class="section-heading">
                <i class="fas fa-list"></i> Κατάλογος Χρηστών
                <span style="margin-left:auto;font-size:0.85rem;font-weight:500;color:var(--text-muted);">
                    Σύνολο:
                    <?= count($users) ?> χρήστες
                </span>
            </div>
            <div class="users-table-wrap">
                <?php if (empty($users)): ?>
                    <div class="no-users"><i class="fas fa-users-slash"></i> Δεν βρέθηκαν χρήστες.</div>
                <?php else: ?>
                    <table class="data-table" id="usersTable">
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
                            <?php foreach ($users as $i => $u): ?>
                                <tr <?= (int) $u['id'] === (int) $logged_in_id ? 'style="background:#fffbeb;"' : '' ?>>
                                    <td>
                                        <?= $i + 1 ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($u['name'] ?? '—') ?>
                                        <?php if ((int) $u['id'] === (int) $logged_in_id): ?>
                                            <span class="badge"
                                                style="background:#fef3c7;color:#92400e;margin-left:6px;">Εσείς</span>
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
                                        <?= $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div><!-- /view-users -->

        <!-- ========== TAB: PROJECT OVERVIEW (from Admin dashboard.html) ========== -->
        <div id="view-dashboard" class="tab-view"
            style="display:<?= $active_tab === 'dashboard' ? 'block' : 'none' ?>;">

            <!-- KPI CARDS -->
            <section class="kpi-grid" style="margin-top:20px;">
                <div class="kpi-card">
                    <div class="icon-box blue"><i class="fas fa-chart-bar"></i></div>
                    <div class="kpi-info">
                        <h4>Συνολικός Προϋπολογισμός</h4>
                        <h2 id="stat-budget">€113.000</h2>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="icon-box orange"><i class="fas fa-clock"></i></div>
                    <div class="kpi-info">
                        <h4>Συνολικό Κόστος</h4>
                        <h2 id="stat-cost">€27.210</h2>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="icon-box green"><i class="fas fa-chart-line"></i></div>
                    <div class="kpi-info">
                        <h4>Συνολικό Κέρδος</h4>
                        <h2 id="stat-profit">+€85.790</h2>
                    </div>
                </div>
            </section>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Αναζήτηση έργου..." onkeyup="filterContent()">
                </div>
                <div class="btn-group">
                    <button class="btn btn-blue" onclick="toggleModal('projectModal')">
                        <i class="fas fa-plus"></i> Νέο Έργο
                    </button>
                </div>
            </div>

            <!-- PROJECT TABS -->
            <nav class="tabs-nav" style="margin-top:10px;">
                <button class="tab-link active" onclick="switchView('active-projects')">Ενεργά Έργα (2)</button>
                <button class="tab-link" onclick="switchView('completed-projects')">Ολοκληρωμένα Έργα (1)</button>
                <button class="tab-link" onclick="switchView('invoices')">Τιμολόγια (4)</button>
                <button class="tab-link" onclick="switchView('employees')">Υπάλληλοι (
                    <?= count($users) ?>)
                </button>
                <button class="tab-link" onclick="switchView('overtime')">Αιτήσεις Επιπλέον Ωρών (2)</button>
            </nav>

            <main id="mainContent"></main>

        </div><!-- /view-dashboard -->

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

        <!-- Modal: Λεπτομέρειες Έργου -->
        <div id="detailsModal" class="modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-title">
                        <h2 id="modalProjName">Εγκατάσταση Κλιματισμού - Hotel Αθήνα</h2>
                        <p><i class="fas fa-map-marker-alt"></i> <span id="modalProjLoc">Αθήνα, Κέντρο</span>
                            | <i class="fas fa-calendar"></i> <span id="modalProjDate">15/11/2024</span></p>
                    </div>
                    <button class="close-modal" onclick="closeDetails()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="details-kpi-grid">
                        <div class="d-card"><span>Συνολικός Προϋπολογισμός</span>
                            <h3 id="det-budget">€50.000</h3><small>(Αρχικός: €45.000)</small>
                        </div>
                        <div class="d-card green-bg"><span>Σύνολο Πληρωμών</span>
                            <h3 id="det-paid">€25.000</h3>
                        </div>
                        <div class="d-card red-bg"><span>Οφειλή Πελάτη</span>
                            <h3 id="det-owed">€25.000</h3>
                        </div>
                        <div class="d-card green-bg"><span>Κέρδος</span>
                            <h3 id="det-profit">+€37.712</h3>
                        </div>
                    </div>
                    <div class="payment-summary-box">
                        <h4><i class="fas fa-file-invoice-dollar"></i> Σύνοψη Πληρωμών</h4>
                        <div class="progress-section">
                            <div class="prog-item">
                                <span>Συνολικό Τιμολόγιο: <strong>€50.000</strong></span>
                                <div class="bar">
                                    <div class="fill blue" style="width:100%"></div>
                                </div>
                            </div>
                            <div class="prog-item">
                                <span>Εισπραχθέντα: <strong class="text-green">€25.000</strong></span>
                                <div class="bar">
                                    <div class="fill green" style="width:50%"></div>
                                </div>
                                <small>50.0% του συνολικού</small>
                            </div>
                            <div class="prog-item">
                                <span>Προς Είσπραξη: <strong class="text-red">€25.000</strong></span>
                                <div class="bar">
                                    <div class="fill red" style="width:50%"></div>
                                </div>
                                <small>50.0% του συνολικού</small>
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
                            <div class="pill blue-pill">Κόστος Εργατοωρών: <strong id="det-labor">€588</strong></div>
                            <div class="pill orange-pill">Κόστος Υλικών: <strong id="det-materials">€11.700</strong>
                            </div>
                            <div class="pill purple-pill">Συνολικό Κόστος: <strong id="det-total-cost">€12.288</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /app-container -->

    <script>
        // ── Tab switching ─────────────────────────────────────────────────────────────
        function switchTab(tabName) {
            document.querySelectorAll('.tab-view').forEach(v => v.style.display = 'none');
            document.querySelectorAll('#mainTabs .tab-link').forEach(b => b.classList.remove('active'));
            const view = document.getElementById('view-' + tabName);
            if (view) view.style.display = 'block';
            const btn = document.getElementById('tab-' + tabName);
            if (btn) btn.classList.add('active');
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            history.replaceState({}, '', url);
        }

        // ── Project sub-tabs ──────────────────────────────────────────────────────────
        const projectData = {
            'active-projects': [
                { name: 'Εγκατάσταση Κλιματισμού - Hotel Αθήνα', location: 'Αθήνα, Κέντρο', date: '15/11/2024', budget: 50000, paid: 25000, profit: 37712 },
                { name: 'Αντικατάσταση Λεβήτων - Εργοστάσιο Πειραιά', location: 'Πειραιάς', date: '03/01/2025', budget: 63000, paid: 20000, profit: 48078 }
            ],
            'completed-projects': [
                { name: 'Συντήρηση HVAC - Νοσοκομείο Θεσσαλονίκης', location: 'Θεσσαλονίκη', date: '10/09/2024', budget: 28000, paid: 28000, profit: 22000 }
            ],
            'invoices': [
                { number: 'ΤΙΜ-001', project: 'Hotel Αθήνα', amount: 25000, status: 'Εξοφλημένο' },
                { number: 'ΤΙΜ-002', project: 'Hotel Αθήνα', amount: 25000, status: 'Εκκρεμεί' },
                { number: 'ΤΙΜ-003', project: 'Εργοστάσιο Πειραιά', amount: 20000, status: 'Εξοφλημένο' },
                { number: 'ΤΙΜ-004', project: 'Εργοστάσιο Πειραιά', amount: 43000, status: 'Εκκρεμεί' }
            ],
            'employees': <?= json_encode(array_map(fn($u) => [
                'name' => $u['name'] ?? '—',
                'role' => $u['role'] ?? '—',
                'email' => $u['email'] ?? '—',
                'phone' => $u['phone'] ?? '—',
                'rate' => $u['hourly_rate'],
            ], $users)) ?>,
                'overtime': [
                    { name: 'Νίκος Καραγιάννης', project: 'Hotel Αθήνα', hours: 3, reason: 'Επείγουσα βλάβη' },
                    { name: 'Κώστας Ιωάννου', project: 'Εργοστάσιο Πειραιά', hours: 2, reason: 'Καθυστέρηση υλικών' }
                ]
};

        let currentView = 'active-projects';

        function switchView(view) {
            document.querySelectorAll('#view-dashboard .tabs-nav .tab-link').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            currentView = view;
            renderContent(view, document.getElementById('globalSearch').value.toLowerCase());
        }

        function filterContent() {
            renderContent(currentView, document.getElementById('globalSearch').value.toLowerCase());
        }

        function renderContent(view, filter) {
            const el = document.getElementById('mainContent');
            filter = filter || '';

            if (view === 'active-projects' || view === 'completed-projects') {
                const data = projectData[view].filter(p =>
                    p.name.toLowerCase().includes(filter) || p.location.toLowerCase().includes(filter)
                );
                el.innerHTML = data.length ? data.map(p => `
            <div class="card">
                <div>
                    <h3>${p.name}</h3>
                    <p><i class="fas fa-map-marker-alt"></i> ${p.location} &nbsp;|&nbsp;
                       <i class="fas fa-calendar"></i> ${p.date}</p>
                </div>
                <div style="display:flex;gap:12px;align-items:center;">
                    <span class="badge badge-admin">€${p.budget.toLocaleString()}</span>
                    <span class="badge badge-helper" style="background:#dcfce7;color:#15803d;">+€${p.profit.toLocaleString()}</span>
                    <button class="btn btn-blue" onclick="openDetails(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                        <i class="fas fa-eye"></i> Λεπτομέρειες
                    </button>
                </div>
            </div>`).join('') : '<div class="no-users">Δεν βρέθηκαν έργα.</div>';

            } else if (view === 'invoices') {
                const data = projectData.invoices.filter(i =>
                    i.number.toLowerCase().includes(filter) || i.project.toLowerCase().includes(filter)
                );
                el.innerHTML = `<div class="invoice-list">${data.map(inv => `
            <div class="invoice-item">
                <div class="inv-info">
                    <i class="fas fa-file-invoice" style="font-size:1.4rem;color:#94a3b8;"></i>
                    <div>
                        <strong>${inv.number}</strong>
                        <p style="margin:0;font-size:0.85rem;color:#64748b;">${inv.project}</p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:16px;">
                    <strong>€${inv.amount.toLocaleString()}</strong>
                    <span class="badge ${inv.status === 'Εξοφλημένο' ? 'badge-helper' : 'badge-foreman'}">${inv.status}</span>
                </div>
            </div>`).join('')}</div>`;

            } else if (view === 'employees') {
                const data = projectData.employees.filter(e =>
                    (e.name || '').toLowerCase().includes(filter) || (e.role || '').toLowerCase().includes(filter)
                );
                const roleBadge = { administrator: 'badge-administrator', supervisor: 'badge-supervisor', helper: 'badge-helper' };
                const roleLabel = { administrator: 'Διαχειριστής', supervisor: 'Υπεύθυνος', helper: 'Βοηθός' };
                el.innerHTML = `<table class="data-table">
            <thead><tr><th>Ονοματεπώνυμο</th><th>Ρόλος</th><th>Email</th><th>Τηλέφωνο</th><th>Ωρομίσθιο</th></tr></thead>
            <tbody>${data.length ? data.map(e => `<tr>
                <td>${e.name || '—'}</td>
                <td><span class="badge ${roleBadge[e.role] || ''}">${roleLabel[e.role] || e.role}</span></td>
                <td>${e.email || '—'}</td>
                <td>${e.phone || '—'}</td>
                <td>${e.rate ? '€' + parseFloat(e.rate).toFixed(2) : '—'}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="no-users">Δεν βρέθηκαν υπάλληλοι.</td></tr>'}</tbody>
        </table>`;

            } else if (view === 'overtime') {
                const data = projectData.overtime;
                el.innerHTML = `<div class="overtime-list">${data.map(ot => `
            <div class="ot-card">
                <div class="ot-header">
                    <div>
                        <strong>${ot.name}</strong>
                        <p style="margin:4px 0 0;color:#64748b;font-size:0.88rem;">${ot.project}</p>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn-approve"><i class="fas fa-check"></i> Έγκριση</button>
                        <button class="btn-reject"><i class="fas fa-times"></i> Απόρριψη</button>
                    </div>
                </div>
                <p><strong>Επιπλέον ώρες:</strong> ${ot.hours}h &nbsp;|&nbsp; <strong>Λόγος:</strong> ${ot.reason}</p>
            </div>`).join('')}</div>`;
            }
        }

        // ── Details Modal ─────────────────────────────────────────────────────────────
        function openDetails(proj) {
            document.getElementById('modalProjName').textContent = proj.name;
            document.getElementById('modalProjLoc').textContent = proj.location;
            document.getElementById('modalProjDate').textContent = proj.date;
            document.getElementById('det-budget').textContent = '€' + proj.budget.toLocaleString();
            document.getElementById('det-paid').textContent = '€' + proj.paid.toLocaleString();
            document.getElementById('det-owed').textContent = '€' + (proj.budget - proj.paid).toLocaleString();
            document.getElementById('det-profit').textContent = '+€' + proj.profit.toLocaleString();
            document.getElementById('paymentHistory').innerHTML = '';
            document.getElementById('adjHistory').innerHTML = '';
            document.getElementById('detailsModal').style.display = 'flex';
        }

        function closeDetails() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function toggleModal(id) {
            const m = document.getElementById(id);
            m.style.display = m.style.display === 'flex' ? 'none' : 'flex';
        }

        function addPaymentRecord() {
            const inv = document.getElementById('payInv').value.trim();
            const amt = document.getElementById('payAmt').value.trim();
            if (!inv || !amt) return;
            const li = document.createElement('li');
            li.className = 'history-item';
            li.innerHTML = `<span>${inv}</span><strong>€${parseFloat(amt).toLocaleString()}</strong>`;
            document.getElementById('paymentHistory').prepend(li);
            document.getElementById('payInv').value = '';
            document.getElementById('payAmt').value = '';
        }

        function adjustBudget() {
            const amt = document.getElementById('adjAmt').value.trim();
            const desc = document.getElementById('adjDesc').value.trim();
            if (!amt) return;
            const li = document.createElement('li');
            li.className = 'history-item';
            li.innerHTML = `<span>${desc || 'Αναπροσαρμογή'}</span><strong style="color:${parseFloat(amt) >= 0 ? 'green' : 'red'};">${parseFloat(amt) >= 0 ? '+' : ''}€${parseFloat(amt).toLocaleString()}</strong>`;
            document.getElementById('adjHistory').prepend(li);
            document.getElementById('adjAmt').value = '';
            document.getElementById('adjDesc').value = '';
        }

        function saveProject() {
            alert('Αποθηκεύτηκε (demo - σύνδεση με βάση δεδομένων απαιτείται).');
            toggleModal('projectModal');
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) this.style.display = 'none';
            });
        });

        // Init
        renderContent('active-projects', '');
    </script>
</body>

</html>