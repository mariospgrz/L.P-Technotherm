/* ── CSRF helper ─────────────────────────────────────────────── */
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ==================== GLOBAL STATE ====================
const appState = {
    user: { fullName: "Διαχειριστής", role: "Admin" },
    kpi: { budget: 0, cost: 0 },   // will be populated from DB
    projects: [],                  // will be populated from DB
    employees: [],                 // will be populated from DB via PHP injection
    invoices: [],                  // will be populated from DB
    overtimeRequests: [],          // will be populated from DB
    currentView: 'active-projects',
    overtimeFilter: 'all'
};

// ==================== INITIALIZATION ====================
window.addEventListener('DOMContentLoaded', function () {
    // Set user name in header (fallback if PHP didn't already set it)
    const nameEl = document.getElementById('account-name');
    if (nameEl && nameEl.textContent.trim() === '') {
        nameEl.textContent = appState.user.fullName;
    }
    // Update KPI
    const profit = appState.kpi.budget - appState.kpi.cost;
    document.getElementById('stat-profit').textContent = `${profit >= 0 ? '+' : ''}€${profit.toLocaleString()}`;
    document.getElementById('stat-budget').textContent = `€${appState.kpi.budget.toLocaleString()}`;
    document.getElementById('stat-cost').textContent = `€${appState.kpi.cost.toLocaleString()}`;

    // Render initial view only when projects panel is visible
    if (document.getElementById('panel-projects') &&
        document.getElementById('panel-projects').style.display !== 'none') {
        renderView(appState.currentView);
    }

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) this.style.display = 'none';
        });
    });
});

// ==================== VIEW RENDERING DISPATCHER ====================
function renderView(viewName) {
    appState.currentView = viewName;

    // Update pill tab active state
    document.querySelectorAll('#panel-projects .tabs-nav .tab-link').forEach(tab => tab.classList.remove('active'));
    const map = {
        'active-projects': 'Ενεργά',
        'completed-projects': 'Ολοκλ',
        'invoices': 'Τιμολ',
        'employees': 'Υπάλλ',
        'overtime': 'Αιτήσ'
    };
    const key = map[viewName] || '';
    const activeTab = Array.from(document.querySelectorAll('#panel-projects .tabs-nav .tab-link'))
        .find(t => t.textContent.includes(key));
    if (activeTab) activeTab.classList.add('active');

    const main = document.getElementById('mainContent');
    main.innerHTML = '';

    switch (viewName) {
        case 'active-projects': renderProjects('active'); break;
        case 'completed-projects': renderProjects('completed'); break;
        case 'invoices': renderInvoices(); break;
        case 'employees': renderEmployees(); break;
        case 'overtime': renderOvertime(appState.overtimeFilter); break;
        default: main.innerHTML = '<p>Επιλέξτε μια καρτέλα</p>';
    }
}

// ==================== PROJECTS ====================
function renderProjects(status) {
    const main = document.getElementById('mainContent');
    const search = (document.getElementById('globalSearch')?.value || '').toLowerCase();
    const filtered = appState.projects.filter(p =>
        p.status === status &&
        (p.name.toLowerCase().includes(search) || p.location.toLowerCase().includes(search))
    );

    if (filtered.length === 0) {
        main.innerHTML = '<div class="no-data"><i class="fas fa-folder-open"></i><br>Δεν βρέθηκαν έργα.</div>';
        return;
    }

    const grid = document.createElement('div');
    grid.className = 'projects-grid';

    filtered.forEach(p => {
        const totalCost = p.costLabor + p.costMaterials;
        const profit = p.budget - totalCost;
        const profitPct = p.budget > 0 ? ((profit / p.budget) * 100).toFixed(1) : 0;
        const usagePct = p.budget > 0 ? Math.min(((totalCost / p.budget) * 100), 100).toFixed(1) : 0;
        const card = document.createElement('div');
        card.className = 'project-card';
        card.innerHTML = `
            <div class="project-card-clickable" onclick="toggleProjectDetails(this)" style="cursor: pointer;">
                <div class="project-card-header">
                    <h3>${p.name}</h3>
                    <span class="badge badge-${status === 'active' ? 'active' : 'completed'}">${status === 'active' ? 'Ενεργό' : 'Ολοκληρωμένο'}</span>
                </div>
                <div class="project-card-meta">
                    <p><i class="fas fa-map-marker-alt"></i> ${p.location}</p>
                    <p><i class="fas fa-calendar-alt"></i> ${p.date}${p.completedAt ? ` - ${p.completedAt}` : ''}</p>
                </div>
                <div class="toggle-indicator" style="text-align: center; color: var(--text-light); padding-bottom: 10px; font-size: 0.85rem; transition: color 0.2s;">
                    <i class="fas fa-chevron-down"></i> <span class="toggle-text">Περισσότερα</span>
                </div>
            </div>
            <div class="project-card-details" style="display: none;">
                <div class="budget-progress-section">
                    <div class="budget-progress-label">
                        <span>Χρήση Προϋπολογισμού</span>
                        <strong>${usagePct}%</strong>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width:${usagePct}%"></div>
                    </div>
                </div>
                <div class="cost-breakdown">
                    <div class="cost-row"><span>Προϋπολογισμός:</span><span>€${p.budget.toLocaleString()}</span></div>
                    <div class="cost-row"><span>Εργατοώρες:</span><span>€${p.costLabor.toLocaleString()}</span></div>
                    <div class="cost-row"><span>Υλικά:</span><span>€${p.costMaterials.toLocaleString()}</span></div>
                    <div class="cost-row"><span>Συνολικό Κόστος:</span><span>€${totalCost.toLocaleString()}</span></div>
                </div>
                <div class="project-profit">
                    <i class="fas fa-chart-line"></i>
                    <span class="profit-label">Κέρδος</span>
                    <div class="profit-value">
                        ${profit >= 0 ? '+' : ''}€${profit.toLocaleString()}
                        <div class="profit-pct">(${profit >= 0 ? '+' : ''}${profitPct}%)</div>
                    </div>
                </div>
                <div class="project-card-actions">
                    <a href="/dashboards/project_details.php?project_id=${p.id}" class="btn btn-green" style="text-decoration:none;">
                        <i class="fas fa-dollar-sign"></i> Λεπτομέρειες
                    </a>
                    <button class="btn btn-blue" onclick="openReportFor(${p.id})">
                        <i class="fas fa-file-alt"></i> Αναφορά
                    </button>
                </div>
            </div>
            ${status === 'active'
                ? `<button class="mark-complete-bar" onclick="changeStatus(${p.id},'completed')">
                       <i class="fas fa-check-circle"></i> Επισήμανση ως Ολοκληρωμένο
                   </button>`
                : `<button class="reactivate-bar" onclick="changeStatus(${p.id},'active')">
                       <i class="fas fa-redo"></i> Επαναφορά σε Ενεργό
                   </button>`
            }
        `;
        grid.appendChild(card);
    });

    main.appendChild(grid);
}

let currentConfirmCallback = null;

function showConfirm(title, message, onConfirm) {
    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalMessage').textContent = message;
    currentConfirmCallback = onConfirm;

    const modal = document.getElementById('confirmModal');
    modal.style.display = 'block';

    // Add event listener to the action button (one-time)
    const actionBtn = document.getElementById('confirmModalActionBtn');
    const newActionBtn = actionBtn.cloneNode(true);
    actionBtn.parentNode.replaceChild(newActionBtn, actionBtn);

    newActionBtn.addEventListener('click', () => {
        if (currentConfirmCallback) currentConfirmCallback();
        toggleModal('confirmModal');
    });
}

function changeStatus(projectId, newStatus) {
    const project = appState.projects.find(p => p.id === projectId);
    if (project) {
        const title = newStatus === 'completed' ? 'Ολοκλήρωση Έργου' : 'Επανενεργοποίηση Έργου';
        const message = newStatus === 'completed'
            ? `Είστε σίγουροι ότι θέλετε να επισημάνετε το έργο "${project.name}" ως ολοκληρωμένο;`
            : `Είστε σίγουροι ότι θέλετε να επαναφέρετε το έργο "${project.name}" σε ενεργό;`;

        showConfirm(title, message, () => {
            fetch('/Backend/ProjectDetails/toggle_project_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: projectId, csrf_token: getCsrf() })
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        project.status = newStatus;
                        project.completedAt = newStatus === 'completed'
                            ? new Date().toLocaleDateString('el-GR')
                            : null;
                        renderView(appState.currentView);
                    } else {
                        alert(res.message || 'Σφάλμα ενημέρωσης');
                    }
                })
                .catch(() => alert('Σφάλμα δικτύου'));
        });
    }
}

// ==================== INVOICES ====================
function renderInvoices() {
    const main = document.getElementById('mainContent');
    const search = (document.getElementById('globalSearch')?.value || '').toLowerCase();
    main.innerHTML = `
        <div class="invoice-search">
            <i class="fas fa-search si"></i>
            <input type="text" id="invoiceSearch" placeholder="Αναζήτηση προμηθευτή ή έργου..."
                   oninput="renderInvoices()" value="${search}">
        </div>
        <div id="invoiceList" class="invoice-list"></div>
    `;
    const term = document.getElementById('invoiceSearch').value.toLowerCase();
    const list = document.getElementById('invoiceList');
    const filtered = appState.invoices.filter(i =>
        i.vendor.toLowerCase().includes(term) || i.project.toLowerCase().includes(term)
    );

    if (filtered.length === 0) {
        list.innerHTML = '<div class="no-data">Δεν βρέθηκαν τιμολόγια.</div>';
        return;
    }

    filtered.forEach(inv => {
        const item = document.createElement('div');
        item.className = 'invoice-item';
        item.innerHTML = `
            <div class="inv-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="inv-info">
                <div class="inv-vendor">${inv.vendor}</div>
                <div class="inv-project"><i class="fas fa-building"></i> ${inv.project}</div>
            </div>
            <div class="inv-right">
                <div class="inv-amount">€${inv.amount.toLocaleString()}</div>
                <div class="inv-date">${inv.date}</div>
            </div>
            <div class="inv-actions">
                <button title="Επεξεργασία" onclick="editInvoice(${inv.id})"><i class="fas fa-pencil-alt"></i></button>
                <button class="del" title="Διαγραφή" onclick="deleteInvoice(${inv.id})"><i class="fas fa-trash"></i></button>
            </div>
        `;
        list.appendChild(item);
    });
}

function editInvoice(id) {
    const inv = appState.invoices.find(i => i.id === id);
    const newVendor = prompt('Νέος προμηθευτής:', inv.vendor);
    if (newVendor !== null) inv.vendor = newVendor;
    const newAmount = prompt('Νέο ποσό (€):', inv.amount);
    if (newAmount !== null) inv.amount = parseFloat(newAmount);
    renderInvoices();
}

function deleteInvoice(id) {
    showConfirm('Διαγραφή Τιμολογίου', 'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το τιμολόγιο;', () => {
        appState.invoices = appState.invoices.filter(i => i.id !== id);
        renderInvoices();
    });
}

// ==================== EMPLOYEES ====================
function renderEmployees() {
    const main = document.getElementById('mainContent');
    const roleLabel = {
        administrator: 'Διαχειριστής', supervisor: 'Υπεύθυνος', helper: 'Βοηθός',
        admin: 'Admin', foreman: 'Επιβλέπων'
    };
    main.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ονοματεπώνυμο</th>
                    <th>Ρόλος</th>
                    <th>Ωρομίσθιο</th>
                    <th>Κανονικές Ώρες</th>
                    <th>Υπερωρίες (Εγκεκριμένες)</th>
                    <th>Συνολικό Κόστος</th>
                </tr>
            </thead>
            <tbody id="empTableBody"></tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Σύνολο</strong></td>
                    <td><strong id="totalHours">0h</strong></td>
                    <td><strong id="totalOvertime">0h</strong></td>
                    <td><strong id="totalCost">€0</strong></td>
                </tr>
            </tfoot>
        </table>
    `;
    const body = document.getElementById('empTableBody');
    let totalH = 0, totalO = 0, totalC = 0;
    appState.employees
        .filter(emp => emp.role === 'supervisor' || emp.role === 'helper')
        .forEach(emp => {
            const cost = emp.rate * (emp.hours + emp.overtime);
            totalH += emp.hours;
            totalO += emp.overtime;
            totalC += cost;
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${emp.name}</td>
            <td><span class="badge badge-${emp.role}">${roleLabel[emp.role] || emp.role}</span></td>
            <td>€${emp.rate}/h</td>
            <td>${emp.hours.toFixed(1)}h</td>
            <td>${emp.overtime.toFixed(1)}h</td>
            <td>€${cost.toFixed(2)}</td>
        `;
            body.appendChild(row);
        });
    document.getElementById('totalHours').textContent = `${totalH.toFixed(1)}h`;
    document.getElementById('totalOvertime').textContent = `${totalO.toFixed(1)}h`;
    document.getElementById('totalCost').textContent = `€${totalC.toFixed(2)}`;
}

function deleteEmp(index) {
    showConfirm('Διαγραφή Υπαλλήλου', 'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτόν τον υπάλληλο;', () => {
        appState.employees.splice(index, 1);
        renderEmployees();
    });
}

// ==================== OVERTIME ====================
function renderOvertime(filter) {
    appState.overtimeFilter = filter;
    const main = document.getElementById('mainContent');
    const pending = appState.overtimeRequests.filter(r => r.status === 'pending').length;
    const approved = appState.overtimeRequests.filter(r => r.status === 'approved').length;
    const rejected = appState.overtimeRequests.filter(r => r.status === 'rejected').length;

    // Calculate days remaining in current month
    const now = new Date();
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const daysRemaining = lastDay.getDate() - now.getDate();

    main.innerHTML = `
        <div class="ot-month-timer" style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:10px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;font-size:0.85rem;color:#0369a1;">
            <i class="fas fa-hourglass-half" style="font-size:1rem;"></i>
            <span>Απομένουν <strong>${daysRemaining}</strong> ${daysRemaining === 1 ? 'ημέρα' : 'ημέρες'} μέχρι το τέλος του μήνα. Μετά το κλείσιμο του μήνα, οι ενέργειες κλειδώνουν.</span>
        </div>
        <div class="sub-tabs">
            <button class="sub-tab ${filter === 'all' ? 'active' : ''}" onclick="renderOvertime('all')">Όλα (${appState.overtimeRequests.length})</button>
            <button class="sub-tab ${filter === 'pending' ? 'active' : ''}" onclick="renderOvertime('pending')">Σε Αναμονή (${pending})</button>
            <button class="sub-tab ${filter === 'approved' ? 'active' : ''}" onclick="renderOvertime('approved')">Εγκρίθηκαν (${approved})</button>
            <button class="sub-tab ${filter === 'rejected' ? 'active' : ''}" onclick="renderOvertime('rejected')">Απορρίφθηκαν (${rejected})</button>
        </div>
        <div id="overtimeList" class="overtime-list"></div>
    `;
    const list = document.getElementById('overtimeList');
    const filtered = appState.overtimeRequests.filter(r => filter === 'all' || r.status === filter);

    if (filtered.length === 0) {
        list.innerHTML = '<div class="no-data" style="padding:30px;text-align:center;color:var(--text-muted);"><i class="fas fa-check-circle" style="font-size:1.5rem;margin-bottom:8px;display:block;"></i>Δεν υπάρχουν αιτήσεις σε αυτή την κατηγορία.</div>';
        return;
    }

    filtered.forEach(r => {
        // Check if the overtime's month has passed (lock if not in current month)
        const isLocked = isOvertimeMonthPassed(r.date);

        const card = document.createElement('div');
        card.className = 'ot-card';
        if (isLocked) card.style.opacity = '0.7';

        let actionsHtml = '';
        if (isLocked) {
            actionsHtml = `<span style="font-size:0.78rem;color:var(--text-muted);display:flex;align-items:center;gap:5px;"><i class="fas fa-lock"></i> Κλειδωμένο</span>`;
        } else if (r.status === 'pending') {
            actionsHtml = `
                <button class="btn-reject"  onclick="updateOvertimeStatus(${r.id},'rejected')">Απόρριψη</button>
                <button class="btn-approve" onclick="updateOvertimeStatus(${r.id},'approved')">Έγκριση</button>
            `;
        } else {
            actionsHtml = `<button class="btn btn-outline" onclick="updateOvertimeStatus(${r.id},'pending')">Αναίρεση</button>`;
        }

        card.innerHTML = `
            <div class="ot-card-info">
                <strong>${r.name}</strong>
                <p class="ot-status ${r.status === 'pending' ? 'ot-status--pending' : r.status === 'approved' ? 'ot-status--approved' : 'ot-status--rejected'}">${r.status === 'pending' ? 'Σε αναμονή' : r.status === 'approved' ? 'Εγκρίθηκε' : 'Απορρίφθηκε'}</p>
                <p><i class="fas fa-building" style="color:var(--primary);margin-right:4px;font-size:0.78rem;"></i> <strong>${r.project || '—'}</strong></p>
                <p>Ώρες: <strong>${r.hours}h</strong> &nbsp;|&nbsp; Ημ/νία: <strong>${r.date || '—'}</strong> &nbsp;|&nbsp; Αιτιολογία: ${r.reason}</p>
            </div>
            <div class="ot-actions">
                ${actionsHtml}
            </div>
        `;
        list.appendChild(card);
    });
}

// Check if the overtime date's month has passed
function isOvertimeMonthPassed(dateStr) {
    if (!dateStr) return false;
    const now = new Date();
    const curYear = now.getFullYear();
    const curMonth = now.getMonth(); // 0-indexed
    // dateStr can be in YYYY-MM-DD or DD/MM/YYYY format
    let otDate;
    if (dateStr.includes('-')) {
        otDate = new Date(dateStr);
    } else if (dateStr.includes('/')) {
        const parts = dateStr.split('/');
        otDate = new Date(parts[2], parts[1] - 1, parts[0]);
    } else {
        return false;
    }
    if (isNaN(otDate.getTime())) return false;
    // Locked if the overtime month is before the current month
    return (otDate.getFullYear() < curYear) || (otDate.getFullYear() === curYear && otDate.getMonth() < curMonth);
}

function updateOvertimeStatus(id, newStatus) {
    fetch('/Backend/Overtime/update_overtime.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status: newStatus, csrf_token: getCsrf() })
    })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const req = appState.overtimeRequests.find(r => r.id === id);
                if (req) { req.status = newStatus; renderOvertime(appState.overtimeFilter); }
            } else {
                alert(res.message || 'Σφάλμα ενημέρωσης');
            }
        })
        .catch(() => alert('Σφάλμα δικτύου'));
}

// ==================== SEARCH ====================
function filterContent() { renderView(appState.currentView); }

// ==================== MODAL CONTROLS ====================
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = (modal.style.display === 'none' || modal.style.display === '') ? 'block' : 'none';
}

// Save new project
function saveProject() {
    const name = document.getElementById('projName').value.trim();
    const loc = document.getElementById('projLocation').value.trim();
    if (!name || !loc) { alert('Συμπληρώστε όνομα και τοποθεσία'); return; }
    const newId = Math.max(...appState.projects.map(p => p.id), 0) + 1;
    appState.projects.push({
        id: newId, name, status: 'active', location: loc,
        date: new Date().toLocaleDateString('el-GR'), budget: 0, paid: 0, costLabor: 0, costMaterials: 0
    });
    toggleModal('projectModal');
    document.getElementById('projName').value = '';
    document.getElementById('projLocation').value = '';
    renderView(appState.currentView);
}

// Save new worker
function saveWorker() {
    const name = document.getElementById('workName').value.trim();
    const role = document.getElementById('workRole').value;
    const rate = parseFloat(document.getElementById('workRate').value);
    if (!name || isNaN(rate) || rate <= 0) { alert('Συμπληρώστε όνομα και έγκυρο ωρομίσθιο'); return; }
    appState.employees.push({ name, role, rate, hours: 0 });
    toggleModal('workerModal');
    document.getElementById('workName').value = '';
    document.getElementById('workRate').value = '';
    if (appState.currentView === 'employees') renderEmployees();
}

// ==================== REPORT MODAL ====================
let reportChartPie = null;
let reportChartBar = null;
let currentReportData = null;

function openReport() { openReportFor(currentProjectId); }

function openReportFor(projectId) {
    currentProjectId = projectId;
    const modal = document.getElementById('reportModal');
    modal.style.display = 'block';

    // Show loading, hide content
    document.getElementById('reportLoading').style.display = 'block';
    document.getElementById('reportContent').style.display = 'none';

    const proj = appState.projects.find(p => p.id === projectId);
    if (proj) {
        document.getElementById('reportProjName').textContent = proj.name;
    }

    // Fetch full data from backend
    fetch(`/Backend/ProjectDetails/get_project_details.php?project_id=${projectId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('reportLoading').innerHTML = `<p style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> ${data.message || 'Σφάλμα φόρτωσης'}</p>`;
                return;
            }
            currentReportData = data;
            populateReport(data);
        })
        .catch(() => {
            document.getElementById('reportLoading').innerHTML = '<p style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> Σφάλμα σύνδεσης</p>';
        });
}

function populateReport(data) {
    const proj = data.project;
    const fin = data.financial_overview;
    const pay = data.payments_summary;
    const roleLabel = { administrator: 'Διαχειριστής', supervisor: 'Υπεύθυνος', helper: 'Βοηθός' };

    // Official Header
    const startDate = proj.start_date ? formatDate(proj.start_date) : '—';
    const endDate = proj.completed_at ? formatDate(proj.completed_at) : null;
    document.getElementById('roh-proj-name').textContent = proj.name;
    document.getElementById('roh-proj-loc').textContent = proj.location;
    document.getElementById('roh-proj-dates').textContent = `${startDate}${endDate ? ' — ' + endDate : ' (Σε εξέλιξη)'}`;
    const statusEl = document.getElementById('roh-proj-status');
    statusEl.textContent = proj.status === 'active' ? 'Ενεργό' : 'Ολοκληρωμένο';
    statusEl.style.color = proj.status === 'active' ? 'var(--primary)' : 'var(--success)';

    // Financial overview - Basic calculations
    const profitVal = parseFloat(fin.profit || 0);
    const budgetVal = parseFloat(fin.total_budget || 0);

    // Initial budget update (other fields are updated later after calculations)
    const budgetEl = document.getElementById('rep-budget');
    if (budgetEl) {
        budgetEl.textContent = `€${budgetVal.toLocaleString('el-GR', { minimumFractionDigits: 2 })}`;
    }

    // Payments summary
    document.getElementById('reportPaymentSummary').innerHTML = `
        <div class="report-info-card"><span class="report-card-label">Τιμολογήθηκε</span><strong class="report-card-value">€${pay.total_invoiced.toLocaleString('el-GR', { minimumFractionDigits: 2 })}</strong></div>
        <div class="report-info-card"><span class="report-card-label">Εισπράχθηκε</span><strong class="report-card-value" style="color:var(--success);">€${pay.total_collected.toLocaleString('el-GR', { minimumFractionDigits: 2 })}</strong></div>
        <div class="report-info-card"><span class="report-card-label">Υπόλοιπο</span><strong class="report-card-value" style="color:${pay.remaining > 0 ? 'var(--danger)' : 'var(--success)'};">€${pay.remaining.toLocaleString('el-GR', { minimumFractionDigits: 2 })}</strong></div>
    `;

    // Payments table
    const paymentsBody = document.getElementById('reportPaymentsBody');
    if (data.recent_payments && data.recent_payments.length > 0) {
        paymentsBody.innerHTML = data.recent_payments.map(p => `<tr>
            <td>${p.payment_date ? formatDate(p.payment_date) : '—'}</td>
            <td>€${parseFloat(p.amount).toLocaleString('el-GR', { minimumFractionDigits: 2 })}</td>
            <td>${escHtml(p.notes || p.description || '—')}</td>
        </tr>`).join('');
    } else {
        paymentsBody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:var(--text-muted);">Δεν υπάρχουν πληρωμές.</td></tr>';
    }

    // Staff table — aggregate hours per user from time_logs
    const staffMap = {};
    if (data.time_logs) {
        data.time_logs.forEach(log => {
            if (!log.clock_out) return;
            const uid = log.user_id;
            if (!staffMap[uid]) {
                staffMap[uid] = { name: log.user_name, role: log.user_role, minutes: 0, rate: parseFloat(log.hourly_rate || 0) };
            }
            const clockIn = new Date(log.clock_in);
            const clockOut = new Date(log.clock_out);
            staffMap[uid].minutes += (clockOut - clockIn) / 60000;
        });
    }
    // Add overtime hours
    if (data.approved_overtime) {
        data.approved_overtime.forEach(ot => {
            const uid = ot.user_id;
            if (!staffMap[uid]) {
                staffMap[uid] = { name: ot.user_name, role: ot.user_role, minutes: 0, rate: parseFloat(ot.hourly_rate || 0) };
            }
            staffMap[uid].minutes += parseFloat(ot.hours) * 60;
        });
    }

    const staffArr = Object.values(staffMap).map(s => ({
        name: s.name,
        role: s.role,
        hours: (s.minutes / 60).toFixed(1),
        rate: s.rate
    }));

    // Get hourly rates from team data
    if (data.team) {
        const rateMap = {};
        // We don't have rate in team data directly, compute cost from fin
        // Use time_logs to compute per-user cost
    }

    const staffBody = document.getElementById('staffTableBody');
    let totalCalculatedLaborCost = 0;

    if (staffArr.length > 0) {
        staffBody.innerHTML = staffArr.map(s => {
            const cost = parseFloat((parseFloat(s.hours) * s.rate).toFixed(2));
            totalCalculatedLaborCost += cost;
            return `<tr>
                <td>${escHtml(s.name)}</td>
                <td>${roleLabel[s.role] || s.role}</td>
                <td>${s.hours} ώρες</td>
                <td>€${cost.toLocaleString('el-GR', { minimumFractionDigits: 2 })}</td>
            </tr>`;
        }).join('');

        // Add totals row using the calculated total
        const totalHours = staffArr.reduce((sum, s) => sum + parseFloat(s.hours), 0).toFixed(1);
        staffBody.innerHTML += `<tr style="background:#fafafa; border-top:2px solid var(--border-color);">
            <td colspan="2"><strong>Σύνολο</strong></td>
            <td><strong>${totalHours} ώρες</strong></td>
            <td><strong>€${totalCalculatedLaborCost.toLocaleString('el-GR', { minimumFractionDigits: 2 })}</strong></td>
        </tr>`;
    } else {
        staffBody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Δεν υπάρχουν εγγραφές ωρών.</td></tr>';
    }

    // Update the Financial Overview card to use the calculated total
    const laborCostEl = document.querySelector('.kpi-card h3:nth-of-type(1)'); 
    // Wait, I need a better selector for the labor cost card. Let's use IDs if possible.
    document.getElementById('reportLaborCost').textContent = `€${totalCalculatedLaborCost.toLocaleString('el-GR', { minimumFractionDigits: 2 })}`;
    
    // Also update total cost card
    const materialCost = parseFloat(fin.material_cost || 0);
    document.getElementById('reportMaterialCost').textContent = `€${materialCost.toLocaleString('el-GR', { minimumFractionDigits: 2 })}`;

    const finalTotalCost = totalCalculatedLaborCost + materialCost;
    document.getElementById('reportTotalCost').textContent = `€${finalTotalCost.toLocaleString('el-GR', { minimumFractionDigits: 2 })}`;
    
    // Update profit card
    const totalBudget = parseFloat(fin.total_budget || 0);
    const finalProfit = totalBudget - finalTotalCost;
    const profitEl = document.getElementById('reportProfit');
    if (profitEl) {
        profitEl.textContent = `€${finalProfit.toLocaleString('el-GR', { minimumFractionDigits: 2 })}`;
        profitEl.style.color = finalProfit >= 0 ? '#10b981' : '#ef4444';
    }

    // Update Percentage (%)
    const pctEl = document.getElementById('rep-pct');
    if (pctEl && totalBudget > 0) {
        const marginPct = ((finalProfit / totalBudget) * 100).toFixed(1);
        pctEl.textContent = `${marginPct}%`;
        pctEl.style.color = marginPct >= 0 ? '#10b981' : '#ef4444';
    }

    // Overtime section
    const otSection = document.getElementById('reportOvertimeSection');
    const otBody = document.getElementById('reportOvertimeBody');
    if (data.approved_overtime && data.approved_overtime.length > 0) {
        otSection.style.display = 'block';
        otBody.innerHTML = data.approved_overtime.map(ot => `<tr>
            <td>${escHtml(ot.user_name)}</td>
            <td>${ot.date ? formatDate(ot.date) : '—'}</td>
            <td>${parseFloat(ot.hours).toFixed(1)} ώρες</td>
            <td>${escHtml(ot.description || '—')}</td>
        </tr>`).join('');
    } else {
        otSection.style.display = 'none';
    }

    // Invoices table
    const invBody = document.getElementById('reportInvoicesBody');
    if (data.invoices && data.invoices.length > 0) {
        invBody.innerHTML = data.invoices.map(inv => `<tr>
            <td>${inv.date ? formatDate(inv.date) : '—'}</td>
            <td>${escHtml(inv.supplier_name || inv.description || '—')}</td>
            <td>€${parseFloat(inv.amount).toLocaleString('el-GR', { minimumFractionDigits: 2 })}</td>
        </tr>`).join('');
        // Totals
        invBody.innerHTML += `<tr style="background:#fafafa; border-top:2px solid var(--border-color);">
            <td colspan="2"><strong>Σύνολο Υλικών</strong></td>
            <td><strong>€${fin.material_cost.toLocaleString('el-GR', { minimumFractionDigits: 2 })}</strong></td>
        </tr>`;
    } else {
        invBody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:var(--text-muted);">Δεν υπάρχουν τιμολόγια.</td></tr>';
    }

    // Charts — destroy old ones first
    if (reportChartPie) { reportChartPie.destroy(); reportChartPie = null; }
    if (reportChartBar) { reportChartBar.destroy(); reportChartBar = null; }

    // Show content, hide loading
    document.getElementById('reportLoading').style.display = 'none';
    document.getElementById('reportContent').style.display = 'block';

    setTimeout(() => {
        const pieCtx = document.getElementById('costPieChart');
        const barCtx = document.getElementById('laborBarChart');
        if (pieCtx) {
            reportChartPie = new Chart(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Υλικά', 'Εργατοώρες'],
                    datasets: [{
                        data: [fin.material_cost, fin.labor_cost],
                        backgroundColor: ['#f59e0b', '#3b82f6'],
                        borderWidth: 0,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 20, font: { size: 11, weight: '500' } } },
                        tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 10, cornerRadius: 8 }
                    }
                }
            });
        }
        if (barCtx && staffArr.length > 0) {
            // Adjust height based on number of employees (min 40px per bar)
            const chartHeight = Math.max(200, staffArr.length * 40);
            document.getElementById('laborChartContainer').style.height = `${chartHeight}px`;

            reportChartBar = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: staffArr.map(s => s.name),
                    datasets: [{
                        label: 'Ώρες',
                        data: staffArr.map(s => parseFloat(s.hours)),
                        backgroundColor: '#3b82f6',
                        borderRadius: 4,
                        barThickness: 22,
                        maxBarThickness: 30
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: 'rgba(0,0,0,0.8)' }
                    },
                    scales: {
                        x: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 11, weight: '500' } } }
                    }
                }
            });
        }
    }, 150);
}

function closeReport() {
    document.getElementById('reportModal').style.display = 'none';
    if (reportChartPie) { reportChartPie.destroy(); reportChartPie = null; }
    if (reportChartBar) { reportChartBar.destroy(); reportChartBar = null; }
}

// ==================== EXPORT PDF ====================
function exportReportPDF() {
    if (!currentReportData) return;
    window.print();
}

// ==================== EXPORT EXCEL ====================
function exportReportExcel() {
    if (!currentReportData) return;
    const data = currentReportData;
    const proj = data.project;
    const fin = data.financial_overview;
    const wb = XLSX.utils.book_new();

    // Sheet 1: Overview
    const overviewData = [
        ['Αναφορά Έργου', proj.name],
        [''],
        ['Τοποθεσία', proj.location],
        ['Ημ. Έναρξης', formatDate(proj.start_date)],
        ['Ημ. Λήξης', proj.completed_at ? formatDate(proj.completed_at) : 'Σε εξέλιξη'],
        ['Κατάσταση', proj.status === 'active' ? 'Ενεργό' : 'Ολοκληρωμένο'],
        [''],
        ['Οικονομική Επισκόπηση'],
        ['Προϋπολογισμός', parseFloat(fin.total_budget)],
        ['Κόστος Εργατοωρών', parseFloat(fin.labor_cost)],
        ['Κόστος Υλικών', parseFloat(fin.material_cost)],
        ['Συνολικό Κόστος', parseFloat(fin.total_cost)],
        ['Κέρδος/Ζημία', parseFloat(fin.profit)],
        [''],
        ['Πληρωμές Πελάτη'],
        ['Τιμολογήθηκε', parseFloat(data.payments_summary.total_invoiced)],
        ['Εισπράχθηκε', parseFloat(data.payments_summary.total_collected)],
        ['Υπόλοιπο', parseFloat(data.payments_summary.remaining)],
    ];
    const ws1 = XLSX.utils.aoa_to_sheet(overviewData);
    ws1['!cols'] = [{ wch: 22 }, { wch: 35 }];
    XLSX.utils.book_append_sheet(wb, ws1, 'Επισκόπηση');

    // Sheet 2: Time Logs (Staff)
    if (data.time_logs && data.time_logs.length > 0) {
        const staffHeaders = ['Όνομα', 'Ρόλος', 'Ημερομηνία', 'Ώρα Εισόδου', 'Ώρα Εξόδου'];
        const staffRows = data.time_logs.map(log => [
            log.user_name,
            log.user_role,
            log.date ? formatDate(log.date) : '',
            log.clock_in || '',
            log.clock_out || ''
        ]);
        const ws2 = XLSX.utils.aoa_to_sheet([staffHeaders, ...staffRows]);
        ws2['!cols'] = [{ wch: 20 }, { wch: 15 }, { wch: 12 }, { wch: 18 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, ws2, 'Εργατοώρες');
    }

    // Sheet 3: Invoices
    if (data.invoices && data.invoices.length > 0) {
        const invHeaders = ['Ημερομηνία', 'Προμηθευτής', 'Ποσό'];
        const invRows = data.invoices.map(inv => [
            inv.date ? formatDate(inv.date) : '',
            inv.supplier_name || inv.description || '',
            parseFloat(inv.amount)
        ]);
        // Add total row
        invRows.push(['', 'ΣΥΝΟΛΟ', parseFloat(fin.material_cost)]);
        
        const ws3 = XLSX.utils.aoa_to_sheet([invHeaders, ...invRows]);
        ws3['!cols'] = [{ wch: 12 }, { wch: 30 }, { wch: 12 }];
        XLSX.utils.book_append_sheet(wb, ws3, 'Τιμολόγια');
    }

    // Sheet 4: Payments
    if (data.recent_payments && data.recent_payments.length > 0) {
        const payHeaders = ['Ημερομηνία', 'Ποσό', 'Σημείωση'];
        const payRows = data.recent_payments.map(p => [
            p.payment_date ? formatDate(p.payment_date) : '',
            parseFloat(p.amount),
            p.notes || p.description || ''
        ]);
        // Add total row
        payRows.push(['', parseFloat(data.payments_summary.total_collected), 'ΣΥΝΟΛΟ']);
        
        const ws4 = XLSX.utils.aoa_to_sheet([payHeaders, ...payRows]);
        ws4['!cols'] = [{ wch: 12 }, { wch: 12 }, { wch: 30 }];
        XLSX.utils.book_append_sheet(wb, ws4, 'Πληρωμές');
    }

    // Sheet 5: Overtime
    if (data.approved_overtime && data.approved_overtime.length > 0) {
        const otHeaders = ['Όνομα', 'Ημερομηνία', 'Ώρες', 'Περιγραφή'];
        const otRows = data.approved_overtime.map(ot => [
            ot.user_name,
            ot.date ? formatDate(ot.date) : '',
            parseFloat(ot.hours),
            ot.description || ''
        ]);
        const ws5 = XLSX.utils.aoa_to_sheet([otHeaders, ...otRows]);
        ws5['!cols'] = [{ wch: 20 }, { wch: 12 }, { wch: 8 }, { wch: 30 }];
        XLSX.utils.book_append_sheet(wb, ws5, 'Υπερωρίες');
    }

    XLSX.writeFile(wb, `Αναφορά_${proj.name.replace(/\s+/g, '_')}.xlsx`);
}

// ==================== REPORT HELPERS ====================
function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('el-GR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ==================== LOGOUT ====================
function handleLogout() {
    window.location.href = '/Backend/logout.php';
}

// ==================== TAB SWITCHING ====================
function switchView(viewName) { renderView(viewName); }

// ==================== MAIN TAB (Users ↔ Projects) ====================
function showMainTab(tab) {
    const usersPanel = document.getElementById('panel-users');
    const projectsPanel = document.getElementById('panel-projects');
    const tabUsers = document.getElementById('mainTabUsers');
    const tabProjects = document.getElementById('mainTabProjects');

    if (usersPanel) usersPanel.style.display = tab === 'users' ? 'block' : 'none';
    if (projectsPanel) projectsPanel.style.display = tab === 'projects' ? 'block' : 'none';
    if (tabUsers) tabUsers.classList.toggle('active', tab === 'users');
    if (tabProjects) tabProjects.classList.toggle('active', tab === 'projects');

    // Keep URL in sync
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    history.replaceState({}, '', url);

    // Init project view when switching to it for the first time
    if (tab === 'projects') renderView(appState.currentView);
}

// Inject real DB employees (set by PHP before this script loads)
if (window.__DB_EMPLOYEES__ && window.__DB_EMPLOYEES__.length) {
    appState.employees = window.__DB_EMPLOYEES__;
}

// Inject real DB projects (set by PHP before this script loads)
if (window.__DB_PROJECTS__ && window.__DB_PROJECTS__.length) {
    appState.projects = window.__DB_PROJECTS__;
    appState.kpi.budget = appState.projects.reduce((sum, p) => sum + p.budget, 0);
    appState.kpi.cost = appState.projects.reduce((sum, p) => sum + p.costLabor + p.costMaterials, 0);
}

// Inject real DB overtime requests (set by PHP before this script loads)
if (window.__DB_OVERTIME__) {
    appState.overtimeRequests = window.__DB_OVERTIME__;
}
// ==================== Create Project ====================

function validateProjectForm() {
    const name = document.getElementById('proj_name').value.trim();
    const location = document.getElementById('proj_location').value.trim();
    const budget = parseFloat(document.getElementById('proj_budget').value);
    const date = document.getElementById('proj_start_date').value;
    const errorBox = document.getElementById('projectFormError');
    const errorMsg = document.getElementById('projectFormErrorMsg');

    function showError(msg) {
        errorMsg.textContent = msg;
        errorBox.style.display = 'block';
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return false;
    }

    errorBox.style.display = 'none';

    if (name.length < 3)
        return showError('Το όνομα έργου πρέπει να έχει τουλάχιστον 3 χαρακτήρες.');
    if (location === '')
        return showError('Η τοποθεσία είναι υποχρεωτική.');
    if (isNaN(budget) || budget <= 0)
        return showError('Ο προϋπολογισμός πρέπει να είναι αριθμός μεγαλύτερος από €0.');
    if (!date)
        return showError('Η ημερομηνία έναρξης είναι υποχρεωτική.');

    return true;
}

// ==================== PROJECT CARD TOGGLE ====================
function toggleProjectDetails(element) {
    const card = element.closest('.project-card');
    const details = card.querySelector('.project-card-details');
    const icon = card.querySelector('.toggle-indicator i');
    const text = card.querySelector('.toggle-indicator .toggle-text');

    if (details.style.display === 'none') {
        details.style.display = 'block';
        icon.className = 'fas fa-chevron-up';
        text.textContent = 'Λιγότερα';
    } else {
        details.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        text.textContent = 'Περισσότερα';
    }
}

// ==================== USERS TABLE SEARCH & SORT ====================
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('userRoleFilter');
    const usersTable = document.getElementById('usersTable');

    if (searchInput && roleFilter && usersTable) {
        const tbody = usersTable.querySelector('tbody');
        const countPill = document.getElementById('usersCountPill');
        const roleMap = { "administrator": "Διαχειριστής", "supervisor": "Υπεύθυνος", "helper": "Βοηθός" };

        function filterUsers() {
            const term = searchInput.value.toLowerCase();
            const roleKey = roleFilter.value;
            const targetRole = roleKey ? roleMap[roleKey] : '';

            let visibleCount = 0;
            const rows = tbody.querySelectorAll('tr');

            rows.forEach(row => {
                if (row.children.length < 8) return;

                const textContent = (
                    row.children[1].textContent + ' ' +
                    row.children[2].textContent + ' ' +
                    row.children[4].textContent + ' ' +
                    row.children[5].textContent
                ).toLowerCase();

                const roleText = row.children[3].textContent.trim();

                const matchesTerm = textContent.includes(term);
                const matchesRole = targetRole === '' || roleText === targetRole;

                if (matchesTerm && matchesRole) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countPill) {
                countPill.textContent = `${visibleCount} χρήστες`;
            }
        }

        searchInput.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);

        // Sorting
        let currentSortCol = -1;
        let isAsc = true;
        const headers = usersTable.querySelectorAll('th[data-sort-type]');

        headers.forEach(th => {
            th.addEventListener('click', () => {
                const col = parseInt(th.getAttribute('data-col'));
                const type = th.getAttribute('data-sort-type');

                if (currentSortCol === col) {
                    isAsc = !isAsc;
                } else {
                    currentSortCol = col;
                    isAsc = true;
                }

                // reset icons
                headers.forEach(h => {
                    const icon = h.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-sort float-right';
                        icon.style.color = '#9ca3af';
                    }
                });
                const icon = th.querySelector('i');
                if (icon) {
                    icon.className = isAsc ? 'fas fa-sort-up float-right' : 'fas fa-sort-down float-right';
                    icon.style.color = '#3b82f6';
                }

                const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.children.length >= 8);

                rows.sort((a, b) => {
                    let valA = a.children[col].textContent.replace('Εσείς', '').trim();
                    let valB = b.children[col].textContent.replace('Εσείς', '').trim();

                    if (type === 'number') {
                        return isAsc ? (parseFloat(valA) - parseFloat(valB)) : (parseFloat(valB) - parseFloat(valA));
                    }
                    else if (type === 'currency') {
                        valA = valA.replace('€', '').trim();
                        valB = valB.replace('€', '').trim();
                        valA = valA === '—' ? 0 : parseFloat(valA);
                        valB = valB === '—' ? 0 : parseFloat(valB);
                        return isAsc ? (valA - valB) : (valB - valA);
                    }
                    else if (type === 'date') {
                        if (valA === '—') return isAsc ? 1 : -1;
                        if (valB === '—') return isAsc ? -1 : 1;
                        const [dA, mA, yA] = valA.split('/');
                        const [dB, mB, yB] = valB.split('/');
                        const dateA = new Date(yA, mA - 1, dA).getTime();
                        const dateB = new Date(yB, mB - 1, dB).getTime();
                        return isAsc ? (dateA - dateB) : (dateB - dateA);
                    }
                    else {
                        valA = valA.toLowerCase();
                        valB = valB.toLowerCase();
                        if (valA < valB) return isAsc ? -1 : 1;
                        if (valA > valB) return isAsc ? 1 : -1;
                        return 0;
                    }
                });

                // re-append
                rows.forEach(r => tbody.appendChild(r));
            });
        });
    }
});

// ==================== EDIT USER POPULATOR ====================
function populateEditForm(userId) {
    if (!window.__DB_USERS_FULL__) return;
    const user = window.__DB_USERS_FULL__.find(u => u.id == userId);
    if (user) {
        document.getElementById('eu_full_name').value = user.name || '';
        document.getElementById('eu_role').value = user.role || '';
        document.getElementById('eu_phone').value = user.phone || '';
        document.getElementById('eu_email').value = user.email || '';
        document.getElementById('eu_hourly_rate').value = user.hourly_rate || '';
    }
}