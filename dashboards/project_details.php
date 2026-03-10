<?php
/**
 * dashboards/project_details.php
 * Admin-only. Full project details page.
 * GET: ?project_id=X
 */
require_once __DIR__ . '/../Backend/admin_session.php';

$project_id = (int) ($_GET['project_id'] ?? 0);
if (!$project_id) {
    header('Location: /dashboards/admin_dashboard.php?tab=projects');
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λεπτομέρειες Έργου | LP Technotherm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <style>
        /* ===== PROJECT DETAILS PAGE ===== */

        /* Header (ROW 1) */
        .pd-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin: 24px 0 20px;
            box-shadow: var(--shadow-sm);
            gap: 16px;
        }

        .pd-header-left h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .pd-header-left .meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.85rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .pd-header-left .meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pd-header-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .btn-back {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-muted);
            font-size: 0.85rem;
            font-family: inherit;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ROW 2: 4 Financial Cards */
        .pd-cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .pd-cards-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 480px) {
            .pd-cards-grid { grid-template-columns: 1fr; }
        }

        .pd-fin-card {
            background: var(--card-bg);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            transition: box-shadow 0.18s, transform 0.18s;
        }

        .pd-fin-card.green-card {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .pd-fin-card.green-card .card-label, .pd-fin-card.green-card .card-amount {
            color: #16a34a;
        }

        .pd-fin-card.red-card {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .pd-fin-card.red-card .card-label, .pd-fin-card.red-card .card-amount {
            color: #ef4444;
        }

        .pd-fin-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .pd-fin-card .card-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .pd-fin-card .card-amount {
            font-size: 1.45rem;
            font-weight: 600;
            color: var(--text-main);
            line-height: 1.2;
        }

        .pd-fin-card .card-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* ROW 3: Payment Summary */
        .pd-summary-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .pd-summary-box h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 18px;
        }

        .pd-progress-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        @media (max-width: 600px) {
            .pd-progress-grid { grid-template-columns: 1fr; }
        }

        .pd-prog-item {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
        }

        .prog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .pd-prog-item .prog-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #4b5563;
        }

        .pd-prog-item .prog-amount {
            font-size: 0.95rem;
            font-weight: 600;
        }

        .pd-prog-item .prog-amount.blue   { color: #1d4ed8; background: transparent; }
        .pd-prog-item .prog-amount.green  { color: #16a34a; background: transparent; }
        .pd-prog-item .prog-amount.red    { color: #ef4444; background: transparent; }

        .pd-prog-item .prog-pct {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* History cards in the forms section */
        .history-card {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .history-card:last-child { margin-bottom: 0; }

        .history-card .hc-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .history-card .hc-date {
            font-size: 0.73rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .history-card .hc-amount {
            font-size: 0.9rem;
            font-weight: 700;
        }

        .history-empty {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.82rem;
            padding: 16px;
        }

        .history-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 16px 0 8px;
        }

        /* m-box label override */
        .m-box label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .m-box .btn {
            width: 100%;
            justify-content: center;
            margin-top: 4px;
        }

        /* ROW 5: Cost Analysis */
        .pd-cost-section {
            margin-top: 8px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
            margin-top: 20px;
        }

        .pd-cost-section h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 16px;
        }

        .pd-cost-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        @media (max-width: 600px) {
            .pd-cost-grid { grid-template-columns: 1fr; }
        }

        .pd-cost-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .pd-cost-card .cc-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pd-cost-card .cc-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .pd-cost-card.blue-cost .cc-amount   { color: var(--primary); }
        .pd-cost-card.orange-cost .cc-amount { color: #f59e0b; }
        .pd-cost-card.purple-cost .cc-amount { color: #8b5cf6; }

        /* Inline form feedback messages */
        .form-msg {
            display: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 500;
            margin-bottom: 12px;
            align-items: center;
            gap: 8px;
        }

        .form-msg.success {
            display: flex;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .form-msg.error {
            display: flex;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* Loading & error states */
        .pd-loading {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-muted);
        }

        .pd-loading i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 16px;
            display: block;
        }

        /* Responsive grids -> single column on mobile */
        @media (max-width: 600px) {
            .management-grid { grid-template-columns: 1fr; }
            .pd-header { flex-direction: column; }
        }

        /* ===== TABS & LISTS ===== */
        .pd-tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .pd-tab {
            padding: 10px 16px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .pd-tab:hover {
            color: var(--primary);
        }

        .pd-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .pd-tab-content {
            display: none;
        }

        .pd-tab-content.active {
            display: block;
        }

        /* List Tables for Tabs */
        .pd-table-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        .pd-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            min-width: 600px;
        }

        .pd-table th {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 20px;
            background: #f9fafb;
            border-bottom: 1px solid var(--border-color);
        }

        .pd-table td {
            font-size: 0.9rem;
            color: var(--text-main);
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .pd-table tr:last-child td {
            border-bottom: none;
        }

        .pd-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .pd-invoice-thumb {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .pd-invoice-thumb:hover {
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <div class="app-container">

        <!-- ========== ROW 1: HEADER ========== -->
        <header class="pd-header">
            <div class="pd-header-left">
                <h1 id="projName">
                    <i class="fas fa-circle-notch fa-spin" style="color:var(--text-light);font-size:1rem;"></i>
                </h1>
                <div class="meta">
                    <span><i class="fas fa-map-marker-alt"></i> <span id="projLocation">—</span></span>
                    <span><i class="fas fa-calendar-alt"></i> <span id="projDate">—</span></span>
                </div>
            </div>
            <div class="pd-header-right">
                <span class="badge" id="projStatus" style="visibility:hidden;padding:5px 14px;font-size:0.78rem;"></span>
                <a href="/dashboards/admin_dashboard.php?tab=projects" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Πίσω
                </a>
            </div>
        </header>

        <!-- Loading state -->
        <div id="loadingState" class="pd-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Φόρτωση δεδομένων έργου…</p>
        </div>

        <!-- Error state -->
        <div id="errorState" style="display:none;" class="no-data">
            <i class="fas fa-exclamation-triangle" style="color:var(--danger);font-size:2rem;display:block;margin-bottom:12px;"></i>
            <p id="errorMsg" style="color:var(--danger);"></p>
            <a href="/dashboards/admin_dashboard.php?tab=projects"
               class="btn btn-blue" style="display:inline-flex;margin-top:18px;">
                <i class="fas fa-arrow-left"></i> Πίσω στα Έργα
            </a>
        </div>

        <!-- Main content (populated by JS) -->
        <div id="pageContent" style="display:none;">

            <!-- Tabs Menu -->
            <div class="pd-tabs">
                <div class="pd-tab active" data-target="tab-overview">Επισκόπηση</div>
                <div class="pd-tab" data-target="tab-time">Ώρες Εργασίας</div>
                <div class="pd-tab" data-target="tab-invoices">Τιμολόγια</div>
                <div class="pd-tab" data-target="tab-team">Ομάδα</div>
            </div>

            <!-- Tab: Overview (Current UI) -->
            <div id="tab-overview" class="pd-tab-content active">

                <!-- ========== ROW 2: 4 Financial Cards ========== -->
                <div class="pd-cards-grid">
                <div class="pd-fin-card">
                    <div class="card-label">Συνολικός Προϋπολογισμός</div>
                    <div class="card-amount" id="fc-budget">—</div>
                    <div class="card-sub" id="fc-budget-original"></div>
                </div>
                <div class="pd-fin-card green-card">
                    <div class="card-label">Σύνολο Πληρωμένων</div>
                    <div class="card-amount" id="fc-paid">—</div>
                </div>
                <div class="pd-fin-card red-card">
                    <div class="card-label">Οφειλή Πελάτη</div>
                    <div class="card-amount" id="fc-debt">—</div>
                </div>
                <div class="pd-fin-card green-card">
                    <div class="card-label">Κέρδος</div>
                    <div class="card-amount" id="fc-profit">—</div>
                </div>
            </div>

            <!-- ========== ROW 3: Payment Summary ========== -->
            <div class="pd-summary-box">
                <h4><i class="fas fa-file-invoice-dollar" style="margin-right:6px;"></i> Σύνοψη Πληρωμών</h4>
                <div class="pd-progress-grid">
                    <div class="pd-prog-item">
                        <div class="prog-header">
                            <div class="prog-label">Συνολικό Τιμολόγιο</div>
                            <div class="prog-amount blue" id="ps-total">—</div>
                        </div>
                        <div class="bar">
                            <div class="fill blue" style="width:100%"></div>
                        </div>
                    </div>
                    <div class="pd-prog-item">
                        <div class="prog-header">
                            <div class="prog-label">Εισπραχθέντα</div>
                            <div class="prog-amount green" id="ps-paid">—</div>
                        </div>
                        <div class="bar">
                            <div class="fill green" id="bar-paid" style="width:0%"></div>
                        </div>
                        <div class="prog-pct" id="ps-paid-pct"></div>
                    </div>
                    <div class="pd-prog-item">
                        <div class="prog-header">
                            <div class="prog-label">Προς Είσπραξη</div>
                            <div class="prog-amount red" id="ps-remaining">—</div>
                        </div>
                        <div class="bar">
                            <div class="fill red" id="bar-remaining" style="width:0%"></div>
                        </div>
                        <div class="prog-pct" id="ps-remaining-pct"></div>
                    </div>
                </div>
            </div>

            <!-- ========== ROW 4: 2 Columns ========== -->
            <div class="management-grid">

                <!-- Left: Payment Form + History -->
                <div class="m-box">
                    <h4><i class="fas fa-cash-register"></i> Καταχώρηση Πληρωμής</h4>
                    <div class="form-msg" id="payment-msg"></div>
                    <form id="paymentForm" onsubmit="submitPayment(event)" novalidate>
                        <label for="payInvoiceNumber">Αριθμός Τιμολογίου</label>
                        <input type="text" id="payInvoiceNumber" name="invoice_number"
                               placeholder="π.χ. ΤΙΜ-001" required>
                        <label for="payAmount">Ποσό Πληρωμής (€)</label>
                        <input type="number" id="payAmount" name="amount"
                               placeholder="0.00" min="0.01" step="0.01" required>
                        <button type="submit" class="btn btn-blue">
                            <i class="fas fa-plus"></i> Προσθήκη Πληρωμής
                        </button>
                    </form>
                    <div class="history-title">Ιστορικό Πληρωμών</div>
                    <div id="paymentHistory">
                        <div class="history-empty">Δεν υπάρχουν πληρωμές.</div>
                    </div>
                </div>

                <!-- Right: Budget Adjustment Form + History -->
                <div class="m-box">
                    <h4><i class="fas fa-edit"></i> Αναπροσαρμογή Προϋπολογισμού</h4>
                    <div class="form-msg" id="adjustment-msg"></div>
                    <form id="adjustmentForm" onsubmit="submitAdjustment(event)" novalidate>
                        <label for="adjAmount">Επιπλέον Ποσό (€)</label>
                        <input type="number" id="adjAmount" name="amount"
                               placeholder="π.χ. +5000 ή -1000" step="0.01" required>
                        <p class="hint">Θετικό για επιπλέον έργα, αρνητικό για μειώσεις</p>
                        <label for="adjDescription">Περιγραφή (προαιρετικό)</label>
                        <textarea id="adjDescription" name="description" rows="3"
                                  placeholder="π.χ. Επιπλέον εργασίες κλιματισμού…"></textarea>
                        <button type="submit" class="btn btn-outline">
                            <i class="fas fa-sync-alt"></i> Ενημέρωση Προϋπολογισμού
                        </button>
                    </form>
                    <div class="history-title">Ιστορικό Αναπροσαρμογών</div>
                    <div id="adjustmentHistory">
                        <div class="history-empty">Δεν υπάρχουν αναπροσαρμογές.</div>
                    </div>
                </div>

            </div><!-- /management-grid -->

            <!-- ========== ROW 5: Cost Analysis ========== -->
            <div class="pd-cost-section">
                <h4>💲 Ανάλυση Κόστους</h4>
                <div class="pd-cost-grid">
                    <div class="pd-cost-card blue-cost">
                        <div class="cc-label">Κόστος Εργατοωρών</div>
                        <div class="cc-amount" id="cc-labor">—</div>
                    </div>
                    <div class="pd-cost-card orange-cost">
                        <div class="cc-label">Κόστος Υλικών</div>
                        <div class="cc-amount" id="cc-materials">—</div>
                    </div>
                    <div class="pd-cost-card purple-cost">
                        <div class="cc-label">Συνολικό Κόστος</div>
                        <div class="cc-amount" id="cc-total">—</div>
                    </div>
                </div>
            </div>

            </div><!-- /tab-overview -->

            <!-- Tab: Time Logs -->
            <div id="tab-time" class="pd-tab-content">
                <div class="pd-table-container">
                    <table class="pd-table">
                        <thead>
                            <tr>
                                <th>Ημερομηνία</th>
                                <th>Εργαζόμενος</th>
                                <th>Ρόλος</th>
                                <th>Είσοδος - Έξοδος</th>
                                <th>Διάρκεια</th>
                            </tr>
                        </thead>
                        <tbody id="timeLogsList">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Invoices -->
            <div id="tab-invoices" class="pd-tab-content">
                <div class="pd-table-container">
                    <table class="pd-table">
                        <thead>
                            <tr>
                                <th>Φωτογραφία</th>
                                <th>Ημερομηνία</th>
                                <th>Προμηθευτής</th>
                                <th>Καταχωρήθηκε από</th>
                                <th>Ποσό</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesList">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Team -->
            <div id="tab-team" class="pd-tab-content">
                <div class="pd-table-container">
                    <table class="pd-table">
                        <thead>
                            <tr>
                                <th>Όνομα</th>
                                <th>Ρόλος</th>
                            </tr>
                        </thead>
                        <tbody id="teamList">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /pageContent -->

    </div><!-- /app-container -->

    <script>
        const PROJECT_ID = <?= $project_id ?>;

        // Tab switching
        document.querySelectorAll('.pd-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.pd-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.pd-tab-content').forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.target).classList.add('active');
            });
        });

        // ── Formatters ────────────────────────────────────────────────────────
        function formatEuro(amount) {
            return '€' + new Intl.NumberFormat('el-GR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('el-GR');
        }

        // ── Inline message ────────────────────────────────────────────────────
        function showMsg(elementId, text, isError) {
            const el = document.getElementById(elementId);
            el.innerHTML = '<i class="fas fa-' + (isError ? 'exclamation-circle' : 'check-circle') + '"></i> ' + text;
            el.className = 'form-msg ' + (isError ? 'error' : 'success');
            clearTimeout(el._timer);
            el._timer = setTimeout(() => { el.className = 'form-msg'; }, 5000);
        }

        // ── Initial load ──────────────────────────────────────────────────────
        async function loadProjectDetails() {
            try {
                const res = await fetch('../Backend/ProjectDetails/get_project_details.php?project_id=' + PROJECT_ID);
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Σφάλμα φόρτωσης.');
                populatePage(data);
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('pageContent').style.display = 'block';
            } catch (err) {
                document.getElementById('loadingState').style.display = 'none';
                document.getElementById('errorState').style.display = 'block';
                document.getElementById('errorMsg').textContent = err.message;
            }
        }

        // Refresh only data sections (after form submit) — no loading flicker
        async function refreshData() {
            try {
                const res = await fetch('../Backend/ProjectDetails/get_project_details.php?project_id=' + PROJECT_ID);
                const data = await res.json();
                if (data.success) populatePage(data);
            } catch (e) { /* silent */ }
        }

        // ── Populate all page sections ────────────────────────────────────────
        function populatePage(data) {
            const p   = data.project;
            const fin = data.financial_overview;
            const pay = data.payments_summary;

            // Header
            document.getElementById('projName').textContent = p.name;
            document.getElementById('projLocation').textContent = p.location;
            document.getElementById('projDate').textContent = formatDate(p.start_date || p.created_at);
            document.title = p.name + ' | LP Technotherm';

            const statusBadge = document.getElementById('projStatus');
            statusBadge.textContent = p.status === 'active' ? 'Ενεργό' : 'Ολοκληρωμένο';
            statusBadge.className = 'badge badge-' + (p.status === 'active' ? 'active' : 'completed');
            statusBadge.style.visibility = 'visible';

            // Financial cards
            document.getElementById('fc-budget').textContent = formatEuro(fin.total_budget);
            document.getElementById('fc-budget-original').textContent =
                '(Αρχικός: ' + formatEuro(fin.original_budget) + ')';
            document.getElementById('fc-paid').textContent = formatEuro(pay.total_collected);
            document.getElementById('fc-debt').textContent = formatEuro(fin.client_debt);

            const profitEl = document.getElementById('fc-profit');
            const profitSign = fin.profit >= 0 ? '↗ +' : '↘ ';
            profitEl.textContent = profitSign + formatEuro(Math.abs(fin.profit));
            profitEl.parentElement.className = 'pd-fin-card ' + (fin.profit >= 0 ? 'green-card' : 'red-card');

            // Payment summary + progress bars
            document.getElementById('ps-total').textContent = formatEuro(pay.total_invoiced);
            document.getElementById('ps-paid').textContent = formatEuro(pay.total_collected);
            document.getElementById('ps-remaining').textContent = formatEuro(pay.remaining);

            const paidPct = pay.total_invoiced > 0
                ? Math.min(Math.round((pay.total_collected / pay.total_invoiced) * 100), 100)
                : 0;
            const remPct = Math.max(100 - paidPct, 0);

            document.getElementById('bar-paid').style.width = paidPct + '%';
            document.getElementById('bar-remaining').style.width = remPct + '%';
            document.getElementById('ps-paid-pct').textContent = paidPct + '% του συνολικού';
            document.getElementById('ps-remaining-pct').textContent = remPct + '% του συνολικού';

            // History lists
            renderPaymentHistory(data.recent_payments);
            renderAdjustmentHistory(data.budget_adjustments);

            // Cost analysis
            document.getElementById('cc-labor').textContent = formatEuro(fin.labor_cost);
            document.getElementById('cc-materials').textContent = formatEuro(fin.material_cost);
            document.getElementById('cc-total').textContent = formatEuro(fin.total_cost);

            // Populate Additional Tabs
            renderTimeLogs(data.time_logs);
            renderInvoices(data.invoices);
            renderTeam(data.team);
        }

        // ── History renderers ─────────────────────────────────────────────────
        function renderPaymentHistory(payments) {
            const container = document.getElementById('paymentHistory');
            if (!payments || payments.length === 0) {
                container.innerHTML = '<div class="history-empty">Δεν υπάρχουν πληρωμές.</div>';
                return;
            }
            container.innerHTML = payments.map(pay => `
                <div class="history-card">
                    <div>
                        <div class="hc-title">${pay.invoice_number}</div>
                        <div class="hc-date">${formatDate(pay.payment_date)}</div>
                    </div>
                    <div class="hc-amount" style="color:#22c55e;">${formatEuro(parseFloat(pay.amount))}</div>
                </div>
            `).join('');
        }

        function renderAdjustmentHistory(adjustments) {
            const container = document.getElementById('adjustmentHistory');
            if (!adjustments || adjustments.length === 0) {
                container.innerHTML = '<div class="history-empty">Δεν υπάρχουν αναπροσαρμογές.</div>';
                return;
            }
            container.innerHTML = adjustments.map(adj => {
                const amt    = parseFloat(adj.amount);
                const color  = amt >= 0 ? '#22c55e' : '#ef4444';
                const prefix = amt >= 0 ? '+' : '';
                const label  = adj.reason || adj.description || '—';
                const by     = adj.created_by_name ? ' · ' + adj.created_by_name : '';
                const date   = formatDate(adj.created_at || adj.date);
                return `
                    <div class="history-card">
                        <div>
                            <div class="hc-title">${label}${by}</div>
                            <div class="hc-date">${date}</div>
                        </div>
                        <div class="hc-amount" style="color:${color};">${prefix}${formatEuro(Math.abs(amt))}</div>
                    </div>
                `;
            }).join('');
        }

        // ── Tab Renderers ─────────────────────────────────────────────────────
        function renderTimeLogs(logs) {
            const tbody = document.getElementById('timeLogsList');
            if (!logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="pd-empty">Δεν υπάρχουν καταγεγραμμένες ώρες.</td></tr>';
                return;
            }
            tbody.innerHTML = logs.map(log => {
                const clockIn = log.clock_in ? new Date(log.clock_in) : null;
                const clockOut = log.clock_out ? new Date(log.clock_out) : null;
                
                let duration = '—';
                if (clockIn && clockOut) {
                    const diffMs = clockOut - clockIn;
                    const hrs = Math.floor(diffMs / 3600000);
                    const mins = Math.round((diffMs % 3600000) / 60000);
                    duration = `${hrs}ω ${mins}λ`;
                }

                const timeIn = clockIn ? clockIn.toLocaleTimeString('el-GR', {hour: '2-digit', minute:'2-digit'}) : '—';
                const timeOut = clockOut ? clockOut.toLocaleTimeString('el-GR', {hour: '2-digit', minute:'2-digit'}) : '—';
                
                let roleName = log.user_role;
                if (roleName === 'helper') roleName = 'Βοηθός';
                if (roleName === 'supervisor') roleName = 'Μάστορας';

                return `
                    <tr>
                        <td>${formatDate(log.date)}</td>
                        <td style="font-weight:600;">${log.user_name || '—'}</td>
                        <td>${roleName}</td>
                        <td>${timeIn} - ${timeOut}</td>
                        <td><strong>${duration}</strong></td>
                    </tr>
                `;
            }).join('');
        }

        function renderInvoices(invoices) {
            const tbody = document.getElementById('invoicesList');
            if (!invoices || invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="pd-empty">Δεν υπάρχουν τιμολόγια έργου.</td></tr>';
                return;
            }
            tbody.innerHTML = invoices.map(inv => {
                const photoPath = inv.photo_path || inv.file_path || inv.image_path; 
                const img = photoPath 
                    ? `<img src="/${photoPath}" class="pd-invoice-thumb" onclick="window.open('/${photoPath}', '_blank')" title="Προβολή">` 
                    : '<div style="width:50px;height:50px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;"><i class="fas fa-image"></i></div>';
                return `
                    <tr>
                        <td>${img}</td>
                        <td>${formatDate(inv.date || inv.created_at)}</td>
                        <td style="font-weight:600;">${inv.supplier_name || '—'}</td>
                        <td>${inv.uploaded_by_name || '—'}</td>
                        <td style="color:#ef4444;font-weight:700;">${formatEuro(parseFloat(inv.amount))}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderTeam(team) {
            const tbody = document.getElementById('teamList');
            if (!team || team.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="pd-empty">Δεν έχει ανατεθεί προσωπικό στο έργο.</td></tr>';
                return;
            }
            tbody.innerHTML = team.map(member => {
                let roleName = member.helper_role;
                if (roleName === 'helper') roleName = 'Βοηθός';
                if (roleName === 'supervisor') roleName = 'Μάστορας';

                return `
                    <tr>
                        <td style="font-weight:600;">${member.helper_name || '—'}</td>
                        <td>${roleName}</td>
                    </tr>
                `;
            }).join('');
        }

        // ── Form submissions ──────────────────────────────────────────────────
        async function submitPayment(e) {
            e.preventDefault();
            const form = e.currentTarget;
            const btn  = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Αποθήκευση…';

            const fd = new FormData(form);
            fd.append('project_id', PROJECT_ID);

            try {
                const res  = await fetch('../Backend/ProjectDetails/add_payment.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showMsg('payment-msg', data.message, false);
                    form.reset();
                    await refreshData();
                } else {
                    showMsg('payment-msg', data.message, true);
                }
            } catch {
                showMsg('payment-msg', 'Σφάλμα σύνδεσης. Δοκιμάστε ξανά.', true);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus"></i> Προσθήκη Πληρωμής';
            }
        }

        async function submitAdjustment(e) {
            e.preventDefault();
            const form = e.currentTarget;
            const btn  = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Αποθήκευση…';

            const fd = new FormData(form);
            fd.append('project_id', PROJECT_ID);

            try {
                const res  = await fetch('../Backend/ProjectDetails/add_budget_adjustment.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showMsg('adjustment-msg', data.message, false);
                    form.reset();
                    await refreshData();
                } else {
                    showMsg('adjustment-msg', data.message, true);
                }
            } catch {
                showMsg('adjustment-msg', 'Σφάλμα σύνδεσης. Δοκιμάστε ξανά.', true);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Ενημέρωση Προϋπολογισμού';
            }
        }

        // ── Init ──────────────────────────────────────────────────────────────
        loadProjectDetails();
    </script>

</body>
</html>
