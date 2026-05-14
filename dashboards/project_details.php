<?php
/**
 * dashboards/project_details.php
 * Admin-only. Full project details page.
 * GET: ?project_id=X
 */
require_once __DIR__ . '/../Backend/admin_session.php';

$project_id = (int) ($_GET['project_id'] ?? 0);
if (!$project_id) {
    header('Location: admin_dashboard.php?tab=projects');
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Λεπτομέρειες Έργου | LP Technotherm</title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="CSS/admin_dashboard.css">
    <link rel="stylesheet" href="CSS/project_details.css">
    <link rel="icon" type="image/jpeg" href="../frontend/images/images.jpg">
</head>

<body>
    <div class="app-container" id="projectDetailsApp" data-project-id="<?= $project_id ?>">

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
                <a href="admin_dashboard.php?tab=projects" class="btn-back">
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
            <a href="admin_dashboard.php?tab=projects" class="btn btn-blue"
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
                    <h4>€ Ανάλυση Κόστους</h4>
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
                                <th class="sortable sort-active" data-sort="date" onclick="sortTimeLogs('date')">
                                    Ημερομηνία <i class="fas fa-sort-down sort-icon"></i></th>
                                <th class="sortable" data-sort="employee" onclick="sortTimeLogs('employee')">Εργαζόμενος
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" data-sort="role" onclick="sortTimeLogs('role')">Ρόλος <i
                                        class="fas fa-sort sort-icon"></i></th>
                                <th>Είσοδος - Έξοδος</th>
                                <th class="sortable" data-sort="duration" onclick="sortTimeLogs('duration')">Διάρκεια <i
                                        class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" data-sort="type" onclick="sortTimeLogs('type')">Τύπος <i
                                        class="fas fa-sort sort-icon"></i></th>
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

                <!-- Filter Bar -->
                <div class="tl-filters">
                    <div class="tl-filters-title"><i class="fas fa-filter"></i> Φίλτρα Τιμολογίων</div>
                    <div class="tl-filters-row">
                        <!-- Global Search -->
                        <div class="tl-filter-group" style="flex: 2;">
                            <label for="invFilterSearch">Αναζήτηση (Προμηθευτής, Χρήστης)</label>
                            <input type="text" id="invFilterSearch" placeholder="π.χ. Υλικά ΟΕ" style="width:100%;"
                                onkeyup="applyInvoiceFilters()">
                        </div>

                        <!-- Date Period -->
                        <div class="tl-filter-group">
                            <label for="invFilterPeriod">Χρονική Περίοδος</label>
                            <select id="invFilterPeriod" onchange="applyInvoiceFilters()" style="width:160px;">
                                <option value="all">Όλα τα τιμολόγια</option>
                                <option value="30">Έως 30 μέρες πριν</option>
                                <option value="90">Τελευταίο 3μηνο</option>
                                <option value="180">Τελευταίο 6μηνο</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="pd-table-container">
                    <table class="pd-table">
                        <thead>
                            <tr>
                                <th>Φωτογραφία</th>
                                <th class="sortable sort-active" data-sort="date" onclick="sortInvoices('date')">
                                    Ημερομηνία <i class="fas fa-sort-down sort-icon"></i></th>
                                <th class="sortable" data-sort="supplier" onclick="sortInvoices('supplier')">Προμηθευτής
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" data-sort="user" onclick="sortInvoices('user')">Καταχωρήθηκε από <i
                                        class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable" data-sort="amount" onclick="sortInvoices('amount')">Ποσό <i
                                        class="fas fa-sort sort-icon"></i></th>
                                <th>Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesList">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- ===== Edit Invoice Modal ===== -->
                <div class="modal-overlay" id="editInvoiceModal">
                    <div class="modal-box">
                        <button class="modal-close" onclick="closeEditInvoiceModal()"><i
                                class="fas fa-times"></i></button>
                        <h3><i class="fas fa-file-invoice" style="color:var(--primary);"></i> Επεξεργασία Τιμολογίου
                        </h3>
                        <div class="form-msg" id="edit-invoice-msg"></div>
                        <form id="editInvoiceForm" onsubmit="submitEditInvoice(event)" novalidate>
                            <input type="hidden" id="editInvId">
                            <div class="modal-field">
                                <label for="editInvSupplier">Προμηθευτής</label>
                                <input type="text" id="editInvSupplier" placeholder="π.χ. ΤΕΧΝΙΚΗ ΑΕ" required>
                            </div>
                            <div class="modal-field">
                                <label for="editInvAmount">Ποσό (€)</label>
                                <input type="number" id="editInvAmount" placeholder="0.00" min="0.01" step="0.01"
                                    required>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-outline" onclick="closeEditInvoiceModal()">
                                    Ακύρωση
                                </button>
                                <button type="submit" class="btn btn-blue" id="editInvSubmitBtn">
                                    <i class="fas fa-save"></i> Αποθήκευση
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ===== Image Viewer Modal ===== -->
                <div class="img-viewer-overlay" id="imgViewerOverlay" onclick="closeImageViewer()">
                    <button class="img-viewer-close" onclick="closeImageViewer()"><i class="fas fa-times"></i></button>
                    <img id="imgViewerImg" src="" alt="Τιμολόγιο" onclick="event.stopPropagation()">
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

    <script src="JS/project_details.js"></script>

</body>

</html>