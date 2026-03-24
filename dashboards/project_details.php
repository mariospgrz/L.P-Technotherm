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
            position: sticky;
            top: 20px;
            z-index: 100;
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
            .pd-cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .pd-cards-grid {
                grid-template-columns: 1fr;
            }
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

        .pd-fin-card.green-card .card-label,
        .pd-fin-card.green-card .card-amount {
            color: #16a34a;
        }

        .pd-fin-card.red-card {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .pd-fin-card.red-card .card-label,
        .pd-fin-card.red-card .card-amount {
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
            .pd-progress-grid {
                grid-template-columns: 1fr;
            }
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

        .pd-prog-item .prog-amount.blue {
            color: #1d4ed8;
            background: transparent;
        }

        .pd-prog-item .prog-amount.green {
            color: #16a34a;
            background: transparent;
        }

        .pd-prog-item .prog-amount.red {
            color: #ef4444;
            background: transparent;
        }

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

        .history-card:last-child {
            margin-bottom: 0;
        }

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
            .pd-cost-grid {
                grid-template-columns: 1fr;
            }
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

        .pd-cost-card.blue-cost .cc-amount {
            color: var(--primary);
        }

        .pd-cost-card.orange-cost .cc-amount {
            color: #f59e0b;
        }

        .pd-cost-card.purple-cost .cc-amount {
            color: #8b5cf6;
        }

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
            .management-grid {
                grid-template-columns: 1fr;
            }

            .pd-header {
                flex-direction: column;
            }
        }

        /* ===== TABS & LISTS ===== */
        .pd-tabs {
            display: flex;
            gap: 20px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 24px;
            overflow-x: auto;
            position: sticky;
            top: 100px;
            z-index: 99;
            background: var(--bg);
            padding-top: 10px;
            margin-top: -10px;
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
            position: sticky;
            top: 0;
            z-index: 1;
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

        /* ===== TIME LOG FILTERS ===== */
        .tl-filters {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .tl-filters-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tl-filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .tl-filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 150px;
        }

        .tl-filter-group label {
            font-size: 0.73rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .tl-filter-group input[type="text"],
        .tl-filter-group select {
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
            font-family: inherit;
            color: var(--text-main);
            background: #fff;
            transition: border-color 0.18s;
            outline: none;
            width: 120px;
        }

        .tl-filter-group input[type="text"]:focus,
        .tl-filter-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        /* Employee multi-select dropdown */
        .tl-emp-dropdown {
            position: relative;
            min-width: 200px;
        }

        .tl-emp-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            color: var(--text-main);
            background: #fff;
            transition: all 0.18s;
            user-select: none;
            gap: 8px;
        }

        .tl-emp-header:hover {
            border-color: var(--primary);
        }

        .tl-emp-header.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .tl-emp-header .tl-dd-icon {
            color: var(--text-muted);
            font-size: 0.7rem;
            transition: transform 0.2s;
        }

        .tl-emp-header.active .tl-dd-icon {
            transform: rotate(180deg);
        }

        .tl-emp-body {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--primary);
            border-top: none;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            z-index: 60;
            display: none;
            flex-direction: column;
        }

        .tl-emp-body.show {
            display: flex;
        }

        .tl-emp-search {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f9fafb;
        }

        .tl-emp-search i {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .tl-emp-search input {
            border: none;
            background: transparent;
            flex: 1;
            font-size: 0.82rem;
            outline: none;
            color: var(--text-main);
            font-family: inherit;
        }

        .tl-emp-list {
            max-height: 180px;
            overflow-y: auto;
            padding: 6px;
        }

        .tl-emp-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.83rem;
            color: var(--text-main);
            transition: background 0.15s;
            text-transform: none;
            letter-spacing: normal;
            font-weight: 500;
        }

        .tl-emp-list label:hover {
            background: #f0f4ff;
        }

        .tl-emp-list input[type="checkbox"] {
            accent-color: var(--primary);
            width: 15px;
            height: 15px;
        }

        .tl-emp-list .tl-emp-role {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-left: auto;
        }

        /* Filter action buttons */
        .tl-filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            margin-left: auto;
        }

        .tl-btn-apply {
            padding: 9px 18px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.18s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tl-btn-apply:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .tl-btn-clear {
            padding: 9px 14px;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.18s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tl-btn-clear:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        .tl-results-info {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 8px;
            padding: 0 4px;
        }

        @media (max-width: 768px) {
            .tl-filters-row {
                flex-direction: column;
            }
            .tl-filter-group {
                width: 100%;
            }
            .tl-filter-actions {
                margin-left: 0;
                width: 100%;
            }
            .tl-btn-apply, .tl-btn-clear {
                flex: 1;
                justify-content: center;
            }
        }

        /* Overtime badge */
        .overtime-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            background: #eff6ff;
            color: #2563eb;
        }
        .normal-badge {
            display: inline-flex;
            align-items: center;
        }

        /* Sortable table headers */
        .pd-table th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 22px;
            transition: color 0.15s;
        }
        .pd-table th.sortable:hover {
            color: #2563eb;
        }
        .pd-table th.sortable .sort-icon {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.65rem;
            opacity: 0.35;
        }
        .pd-table th.sortable.sort-active .sort-icon {
            opacity: 1;
            color: #2563eb;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            background: #f0fdf4;
            color: #16a34a;
        }
    </style>
    <link rel="icon" type="image/jpeg" href="/frontend/images/images.jpg">
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
                <span class="badge" id="projStatus"
                    style="visibility:hidden;padding:5px 14px;font-size:0.78rem;"></span>
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
            <i class="fas fa-exclamation-triangle"
                style="color:var(--danger);font-size:2rem;display:block;margin-bottom:12px;"></i>
            <p id="errorMsg" style="color:var(--danger);"></p>
            <a href="/dashboards/admin_dashboard.php?tab=projects" class="btn btn-blue"
                style="display:inline-flex;margin-top:18px;">
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
                            <input type="text" id="payInvoiceNumber" name="invoice_number" placeholder="π.χ. ΤΙΜ-001"
                                required>
                            <label for="payAmount">Ποσό Πληρωμής (€)</label>
                            <input type="number" id="payAmount" name="amount" placeholder="0.00" min="0.01" step="0.01"
                                required>
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
                            <input type="number" id="adjAmount" name="amount" placeholder="π.χ. +5000 ή -1000"
                                step="0.01" required>
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

                <!-- Filter Bar -->
                <div class="tl-filters">
                    <div class="tl-filters-title"><i class="fas fa-filter"></i> Φίλτρα</div>
                    <div class="tl-filters-row">

                        <!-- Date From -->
                        <div class="tl-filter-group">
                            <label for="tlFilterDateFrom">Από</label>
                            <input type="text" id="tlFilterDateFrom" placeholder="ηη/μμ/εεεε" maxlength="10">
                        </div>

                        <!-- Date To -->
                        <div class="tl-filter-group">
                            <label for="tlFilterDateTo">Έως</label>
                            <input type="text" id="tlFilterDateTo" placeholder="ηη/μμ/εεεε" maxlength="10">
                        </div>

                        <!-- Employee Multi-Select -->
                        <div class="tl-filter-group">
                            <label>Υπάλληλοι</label>
                            <div class="tl-emp-dropdown" id="tlEmpDropdown">
                                <div class="tl-emp-header" id="tlEmpHeader">
                                    <span id="tlEmpHeaderText">Όλοι οι υπάλληλοι</span>
                                    <i class="fas fa-chevron-down tl-dd-icon"></i>
                                </div>
                                <div class="tl-emp-body" id="tlEmpBody">
                                    <div class="tl-emp-search">
                                        <i class="fas fa-search"></i>
                                        <input type="text" id="tlEmpSearch" placeholder="Αναζήτηση υπαλλήλου…">
                                    </div>
                                    <div class="tl-emp-list" id="tlEmpList">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Role Filter -->
                        <div class="tl-filter-group">
                            <label for="tlFilterRole">Ρόλος</label>
                            <select id="tlFilterRole">
                                <option value="">Όλοι</option>
                                <option value="supervisor">Μάστορας</option>
                                <option value="helper">Βοηθός</option>
                            </select>
                        </div>

                        <!-- Type Filter (Normal / Overtime) -->
                        <div class="tl-filter-group">
                            <label for="tlFilterType">Τύπος</label>
                            <select id="tlFilterType">
                                <option value="">Όλα</option>
                                <option value="normal">Κανονική Εργασία</option>
                                <option value="overtime">Υπερωρία</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="tl-filter-actions">
                            <button type="button" class="tl-btn-apply" onclick="applyTimeFilters()">
                                <i class="fas fa-search"></i> Εφαρμογή
                            </button>
                            <button type="button" class="tl-btn-clear" onclick="clearTimeFilters()">
                                <i class="fas fa-times"></i> Καθαρισμός
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Results info -->
                <div id="tlResultsInfo" class="tl-results-info" style="display:none;"></div>

                <div class="pd-table-container">
                    <table class="pd-table">
                        <thead>
                            <tr>
                                <th class="sortable sort-active" data-sort="date" onclick="sortTimeLogs('date')">Ημερομηνία <i class="fas fa-sort-down sort-icon"></i></th>
                                <th class="sortable" data-sort="employee" onclick="sortTimeLogs('employee')">Εργαζόμενος <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" data-sort="role" onclick="sortTimeLogs('role')">Ρόλος <i class="fas fa-sort sort-icon"></i></th>
                                <th>Είσοδος - Έξοδος</th>
                                <th class="sortable" data-sort="duration" onclick="sortTimeLogs('duration')">Διάρκεια <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" data-sort="type" onclick="sortTimeLogs('type')">Τύπος <i class="fas fa-sort sort-icon"></i></th>
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
        let allTimeLogs = [];

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
            const p = data.project;
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
            // Merge normal time logs + approved overtime into one array
            const normalLogs = (data.time_logs || []).map(log => ({ ...log, entry_type: 'normal' }));
            const overtimeLogs = (data.approved_overtime || []).map(ot => ({
                date: ot.date,
                user_name: ot.user_name,
                user_role: ot.user_role,
                clock_in: null,
                clock_out: null,
                overtime_hours: parseFloat(ot.hours),
                description: ot.description,
                entry_type: 'overtime'
            }));
            allTimeLogs = [...normalLogs, ...overtimeLogs];
            applyTimeFilters();
            populateEmployeeDropdown(allTimeLogs);
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
                const amt = parseFloat(adj.amount);
                const color = amt >= 0 ? '#22c55e' : '#ef4444';
                const prefix = amt >= 0 ? '+' : '';
                const label = adj.reason || adj.description || '—';
                const by = adj.created_by_name ? ' · ' + adj.created_by_name : '';
                const date = formatDate(adj.created_at || adj.date);
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
                tbody.innerHTML = '<tr><td colspan="6" class="pd-empty">Δεν υπάρχουν καταγεγραμμένες ώρες.</td></tr>';
                return;
            }
            tbody.innerHTML = logs.map(log => {
                const isOvertime = log.entry_type === 'overtime';
                const rowClass = isOvertime ? 'overtime-row' : '';

                let duration = '—';
                let timeIn = '—';
                let timeOut = '—';

                if (isOvertime) {
                    const hrs = Math.floor(log.overtime_hours);
                    const mins = Math.round((log.overtime_hours - hrs) * 60);
                    duration = `${hrs}ω ${mins}λ`;
                    timeIn = 'Υπερωρία';
                    timeOut = '';
                } else {
                    const clockIn = log.clock_in ? new Date(log.clock_in) : null;
                    const clockOut = log.clock_out ? new Date(log.clock_out) : null;
                    if (clockIn && clockOut) {
                        const diffMs = clockOut - clockIn;
                        const hrs = Math.floor(diffMs / 3600000);
                        const mins = Math.round((diffMs % 3600000) / 60000);
                        duration = `${hrs}ω ${mins}λ`;
                    }
                    timeIn = clockIn ? clockIn.toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' }) : '—';
                    timeOut = clockOut ? clockOut.toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' }) : '—';
                }

                let roleName = log.user_role;
                if (roleName === 'helper') roleName = 'Βοηθός';
                if (roleName === 'supervisor') roleName = 'Μάστορας';

                const typeBadge = isOvertime
                    ? '<span class="overtime-badge">Υπερωρία</span>'
                    : '<span class="normal-badge">Κανονική</span>';

                const timeDisplay = isOvertime ? 'Υπερωρία' : `${timeIn} - ${timeOut}`;

                return `
                    <tr class="${rowClass}">
                        <td>${formatDate(log.date)}</td>
                        <td style="font-weight:600;">${log.user_name || '—'}</td>
                        <td>${roleName}</td>
                        <td>${timeDisplay}</td>
                        <td><strong>${duration}</strong></td>
                        <td>${typeBadge}</td>
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
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Αποθήκευση…';

            const fd = new FormData(form);
            fd.append('project_id', PROJECT_ID);

            try {
                const res = await fetch('../Backend/ProjectDetails/add_payment.php', { method: 'POST', body: fd });
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
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Αποθήκευση…';

            const fd = new FormData(form);
            fd.append('project_id', PROJECT_ID);

            try {
                const res = await fetch('../Backend/ProjectDetails/add_budget_adjustment.php', { method: 'POST', body: fd });
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

        // ── Time Log Filters ──────────────────────────────────────────────────
        let currentSort = { field: 'date', direction: 'desc' };

        function sortTimeLogs(field) {
            if (currentSort.field === field) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.field = field;
                currentSort.direction = 'asc';
            }

            // Update UI headers
            document.querySelectorAll('.pd-table th.sortable').forEach(th => {
                const icon = th.querySelector('.sort-icon');
                th.classList.remove('sort-active');
                icon.className = 'fas fa-sort sort-icon';
                
                if (th.dataset.sort === field) {
                    th.classList.add('sort-active');
                    icon.className = `fas fa-sort-${currentSort.direction === 'asc' ? 'up' : 'down'} sort-icon`;
                }
            });

            applyTimeFilters();
        }

        function populateEmployeeDropdown(logs) {
            const listEl = document.getElementById('tlEmpList');
            const seen = new Map();
            logs.forEach(log => {
                if (log.user_name && !seen.has(log.user_name)) {
                    seen.set(log.user_name, log.user_role);
                }
            });
            listEl.innerHTML = '';
            seen.forEach((role, name) => {
                let roleLabel = role === 'supervisor' ? 'Μάστορας' : 'Βοηθός';
                const lbl = document.createElement('label');
                lbl.innerHTML = `<input type="checkbox" value="${name}"> ${name} <span class="tl-emp-role">${roleLabel}</span>`;
                listEl.appendChild(lbl);
            });
        }

        // Parse dd/mm/yyyy to yyyy-mm-dd for comparison
        function parseDDMMYYYY(str) {
            if (!str) return null;
            const parts = str.split('/');
            if (parts.length !== 3) return null;
            const d = parts[0].padStart(2, '0');
            const m = parts[1].padStart(2, '0');
            const y = parts[2];
            if (y.length !== 4 || isNaN(+d) || isNaN(+m) || isNaN(+y)) return null;
            return `${y}-${m}-${d}`;
        }

        // Auto-format date input: add / after dd and mm
        function autoFormatDate(input) {
            input.addEventListener('input', function () {
                let v = this.value.replace(/[^0-9]/g, '');
                if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
                if (v.length > 5) v = v.substring(0, 5) + '/' + v.substring(5);
                if (v.length > 10) v = v.substring(0, 10);
                this.value = v;
            });
        }
        autoFormatDate(document.getElementById('tlFilterDateFrom'));
        autoFormatDate(document.getElementById('tlFilterDateTo'));

        function applyTimeFilters() {
            const dateFromRaw = document.getElementById('tlFilterDateFrom').value;
            const dateToRaw = document.getElementById('tlFilterDateTo').value;
            const dateFrom = parseDDMMYYYY(dateFromRaw);
            const dateTo = parseDDMMYYYY(dateToRaw);
            const role = document.getElementById('tlFilterRole').value;
            const entryType = document.getElementById('tlFilterType').value;
            const selectedEmps = Array.from(document.querySelectorAll('#tlEmpList input[type=checkbox]:checked')).map(cb => cb.value);

            let filtered = allTimeLogs.filter(log => {
                // Date range
                if (dateFrom && log.date < dateFrom) return false;
                if (dateTo && log.date > dateTo) return false;
                // Role
                if (role && log.user_role !== role) return false;
                // Type
                if (entryType && log.entry_type !== entryType) return false;
                // Employee
                if (selectedEmps.length > 0 && !selectedEmps.includes(log.user_name)) return false;
                return true;
            });

            // Apply sorting
            filtered.sort((a, b) => {
                let valA, valB;
                
                if (currentSort.field === 'date') {
                    valA = a.date;
                    valB = b.date;
                } else if (currentSort.field === 'employee') {
                    valA = a.user_name || '';
                    valB = b.user_name || '';
                } else if (currentSort.field === 'role') {
                    valA = a.user_role || '';
                    valB = b.user_role || '';
                } else if (currentSort.field === 'type') {
                    valA = a.entry_type || '';
                    valB = b.entry_type || '';
                } else if (currentSort.field === 'duration') {
                    valA = a.entry_type === 'overtime' ? a.overtime_hours : 0;
                    if (a.entry_type === 'normal' && a.clock_in && a.clock_out) {
                        valA = (new Date(a.clock_out) - new Date(a.clock_in)) / 3600000;
                    }
                    valB = b.entry_type === 'overtime' ? b.overtime_hours : 0;
                    if (b.entry_type === 'normal' && b.clock_in && b.clock_out) {
                        valB = (new Date(b.clock_out) - new Date(b.clock_in)) / 3600000;
                    }
                }

                if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });

            renderTimeLogs(filtered);

            // Results info
            const info = document.getElementById('tlResultsInfo');
            if (dateFrom || dateTo || role || entryType || selectedEmps.length > 0) {
                info.style.display = 'block';
                info.innerHTML = '<i class="fas fa-info-circle"></i> Εμφανίζονται <strong>' + filtered.length + '</strong> από <strong>' + allTimeLogs.length + '</strong> εγγραφές';
            } else {
                info.style.display = 'none';
            }
        }

        function clearTimeFilters() {
            document.getElementById('tlFilterDateFrom').value = '';
            document.getElementById('tlFilterDateTo').value = '';
            document.getElementById('tlFilterRole').value = '';
            document.getElementById('tlFilterType').value = '';
            document.querySelectorAll('#tlEmpList input[type=checkbox]').forEach(cb => cb.checked = false);
            document.getElementById('tlEmpHeaderText').textContent = 'Όλοι οι υπάλληλοι';
            document.getElementById('tlResultsInfo').style.display = 'none';
            
            // Reset sorting
            currentSort = { field: 'date', direction: 'desc' };
            document.querySelectorAll('.pd-table th.sortable').forEach(th => {
                const icon = th.querySelector('.sort-icon');
                th.classList.remove('sort-active');
                icon.className = 'fas fa-sort sort-icon';
                if (th.dataset.sort === 'date') {
                    th.classList.add('sort-active');
                    icon.className = 'fas fa-sort-down sort-icon';
                }
            });

            applyTimeFilters();
        }

        // Employee dropdown toggle
        document.getElementById('tlEmpHeader').addEventListener('click', () => {
            const header = document.getElementById('tlEmpHeader');
            const body = document.getElementById('tlEmpBody');
            header.classList.toggle('active');
            body.classList.toggle('show');
        });

        // Employee search
        document.getElementById('tlEmpSearch').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#tlEmpList label').forEach(label => {
                const name = label.textContent.toLowerCase();
                label.style.display = name.includes(q) ? '' : 'none';
            });
        });

        // Update header text on checkbox change
        document.getElementById('tlEmpList').addEventListener('change', () => {
            const checked = document.querySelectorAll('#tlEmpList input[type=checkbox]:checked');
            const headerText = document.getElementById('tlEmpHeaderText');
            if (checked.length === 0) {
                headerText.textContent = 'Όλοι οι υπάλληλοι';
            } else if (checked.length === 1) {
                headerText.textContent = checked[0].value;
            } else {
                headerText.textContent = checked.length + ' υπάλληλοι επιλεγμένοι';
            }
        });

        // Close dropdown on outside click
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('tlEmpDropdown');
            if (!dropdown.contains(e.target)) {
                document.getElementById('tlEmpHeader').classList.remove('active');
                document.getElementById('tlEmpBody').classList.remove('show');
            }
        });

        // ── Init ──────────────────────────────────────────────────────────────
        loadProjectDetails();
    </script>

</body>

</html>