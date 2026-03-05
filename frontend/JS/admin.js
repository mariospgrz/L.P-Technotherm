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
    document.getElementById('stat-profit').textContent = `+€${profit.toLocaleString()}`;
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
            <div class="project-card-header">
                <h3>${p.name}</h3>
                <span class="badge badge-${status === 'active' ? 'active' : 'completed'}">${status === 'active' ? 'Ενεργό' : 'Ολοκληρωμένο'}</span>
            </div>
            <div class="project-card-meta">
                <p><i class="fas fa-map-marker-alt"></i> ${p.location}</p>
                <p><i class="fas fa-calendar-alt"></i> ${p.date}</p>
            </div>
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
                    +€${profit.toLocaleString()}
                    <div class="profit-pct">(+${profitPct}%)</div>
                </div>
            </div>
            <div class="project-card-actions">
                <button class="btn btn-green" onclick="openDetails(${p.id})">
                    <i class="fas fa-dollar-sign"></i> Λεπτομέρειες
                </button>
                <button class="btn btn-blue" onclick="openReportFor(${p.id})">
                    <i class="fas fa-file-alt"></i> Αναφορά
                </button>
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

function changeStatus(projectId, newStatus) {
    const project = appState.projects.find(p => p.id === projectId);
    if (project) {
        project.status = newStatus;
        renderView(appState.currentView);
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
    if (confirm('Διαγραφή αυτού του τιμολογίου;')) {
        appState.invoices = appState.invoices.filter(i => i.id !== id);
        renderInvoices();
    }
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
                    <th>Συνολικές Ώρες</th>
                    <th>Συνολικό Κόστος</th>
                    <th>Ενέργειες</th>
                </tr>
            </thead>
            <tbody id="empTableBody"></tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Σύνολο</strong></td>
                    <td><strong id="totalHours">0h</strong></td>
                    <td><strong id="totalCost">€0</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    `;
    const body = document.getElementById('empTableBody');
    let totalH = 0, totalC = 0;
    appState.employees.forEach((emp, index) => {
        const cost = emp.rate * emp.hours;
        totalH += emp.hours;
        totalC += cost;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${emp.name}</td>
            <td><span class="badge badge-${emp.role}">${roleLabel[emp.role] || emp.role}</span></td>
            <td>€${emp.rate}/h</td>
            <td>${emp.hours}h</td>
            <td>€${cost.toFixed(2)}</td>
            <td><button class="btn-delete" onclick="deleteEmp(${index})"><i class="fas fa-trash"></i> Διαγραφή</button></td>
        `;
        body.appendChild(row);
    });
    document.getElementById('totalHours').textContent = `${totalH.toFixed(1)}h`;
    document.getElementById('totalCost').textContent = `€${totalC.toFixed(2)}`;
}

function deleteEmp(index) {
    if (confirm('Διαγραφή υπαλλήλου;')) {
        appState.employees.splice(index, 1);
        renderEmployees();
    }
}

// ==================== OVERTIME ====================
function renderOvertime(filter) {
    appState.overtimeFilter = filter;
    const main = document.getElementById('mainContent');
    const pending = appState.overtimeRequests.filter(r => r.status === 'pending').length;
    const approved = appState.overtimeRequests.filter(r => r.status === 'approved').length;
    main.innerHTML = `
        <div class="sub-tabs">
            <button class="sub-tab ${filter === 'all' ? 'active' : ''}" onclick="renderOvertime('all')">Όλα (${appState.overtimeRequests.length})</button>
            <button class="sub-tab ${filter === 'pending' ? 'active' : ''}" onclick="renderOvertime('pending')">Σε Αναμονή (${pending})</button>
            <button class="sub-tab ${filter === 'approved' ? 'active' : ''}" onclick="renderOvertime('approved')">Εγκρίθηκαν (${approved})</button>
        </div>
        <div id="overtimeList" class="overtime-list"></div>
    `;
    const list = document.getElementById('overtimeList');
    const filtered = appState.overtimeRequests.filter(r => filter === 'all' || r.status === filter);
    filtered.forEach(r => {
        const card = document.createElement('div');
        card.className = 'ot-card';
        card.innerHTML = `
            <div class="ot-header">
                <div>
                    <strong>${r.name}</strong>
                    <p>${r.status === 'pending' ? '🟡 Σε αναμονή' : '✅ Εγκρίθηκε'}</p>
                </div>
                <div>
                    ${r.status === 'pending' ? `
                        <button class="btn-reject"  onclick="updateOvertimeStatus(${r.id},'rejected')">Απόρριψη</button>
                        <button class="btn-approve" onclick="updateOvertimeStatus(${r.id},'approved')">Έγκριση</button>
                    ` : `<button class="btn btn-outline" onclick="updateOvertimeStatus(${r.id},'pending')">Αναίρεση</button>`}
                </div>
            </div>
            <p>Ώρες: <strong>${r.hours}h</strong> &nbsp;|&nbsp; Αιτιολογία: ${r.reason}</p>
        `;
        list.appendChild(card);
    });
}

function updateOvertimeStatus(id, newStatus) {
    const req = appState.overtimeRequests.find(r => r.id === id);
    if (req) { req.status = newStatus; renderOvertime(appState.overtimeFilter); }
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

// ==================== PROJECT DETAILS MODAL ====================
let currentProjectId = null;

function openDetails(projectId) {
    currentProjectId = projectId;
    const proj = appState.projects.find(p => p.id === projectId);
    if (!proj) return;

    document.getElementById('modalProjName').textContent = proj.name;
    document.getElementById('modalProjLoc').textContent = proj.location;
    document.getElementById('modalProjDate').textContent = proj.date;
    document.getElementById('det-budget').textContent = `€${proj.budget.toLocaleString()}`;
    document.getElementById('det-paid').textContent = `€${proj.paid.toLocaleString()}`;
    document.getElementById('det-owed').textContent = `€${(proj.budget - proj.paid).toLocaleString()}`;
    document.getElementById('det-profit').textContent = `€${(proj.budget - proj.costLabor - proj.costMaterials).toLocaleString()}`;
    document.getElementById('det-labor').textContent = `€${proj.costLabor.toLocaleString()}`;
    document.getElementById('det-materials').textContent = `€${proj.costMaterials.toLocaleString()}`;
    document.getElementById('det-total-cost').textContent = `€${(proj.costLabor + proj.costMaterials).toLocaleString()}`;

    // Progress bars
    const pct = proj.budget > 0 ? Math.round((proj.paid / proj.budget) * 100) : 0;
    const owed = proj.budget - proj.paid;
    if (document.getElementById('ps-total')) {
        document.getElementById('ps-total').textContent = `€${proj.budget.toLocaleString()}`;
        document.getElementById('ps-paid').textContent = `€${proj.paid.toLocaleString()}`;
        document.getElementById('ps-owed').textContent = `€${owed.toLocaleString()}`;
        document.getElementById('ps-paid-pct').textContent = `${pct}% του συνολικού`;
        document.getElementById('ps-owed-pct').textContent = `${100 - pct}% του συνολικού`;
        document.getElementById('bar-paid').style.width = `${pct}%`;
        document.getElementById('bar-owed').style.width = `${100 - pct}%`;
    }

    // Mock payment history
    document.getElementById('paymentHistory').innerHTML = `
        <li class="history-item"><span>ΤΙΜ-2024-002 <br><small>28/11/2024</small></span><strong>€10.000</strong></li>
        <li class="history-item"><span>ΤΙΜ-2024-001 <br><small>20/11/2024</small></span><strong>€15.000</strong></li>
    `;
    document.getElementById('adjHistory').innerHTML = `
        <li class="history-item"><span>Επιπλέον υλικά <br><small>22/11/2024</small></span><strong>+€5.000</strong></li>
    `;

    document.getElementById('detailsModal').style.display = 'block';
}

function closeDetails() { document.getElementById('detailsModal').style.display = 'none'; }

function addPaymentRecord() {
    const inv = document.getElementById('payInv').value;
    const amt = parseFloat(document.getElementById('payAmt').value);
    if (!inv || isNaN(amt) || amt <= 0) { alert('Συμπληρώστε αριθμό τιμολογίου και ποσό'); return; }
    const li = document.createElement('li');
    li.className = 'history-item';
    li.innerHTML = `<span>${inv}</span><strong>€${amt.toLocaleString()}</strong>`;
    document.getElementById('paymentHistory').prepend(li);
    document.getElementById('payInv').value = '';
    document.getElementById('payAmt').value = '';
}

function adjustBudget() {
    const amt = parseFloat(document.getElementById('adjAmt').value);
    const desc = document.getElementById('adjDesc').value || 'Αναπροσαρμογή';
    if (isNaN(amt)) { alert('Συμπληρώστε ποσό'); return; }
    const li = document.createElement('li');
    li.className = 'history-item';
    li.innerHTML = `<span>${desc}</span><strong style="color:${amt >= 0 ? 'var(--success)' : 'var(--danger)'};">${amt >= 0 ? '+' : ''}€${Math.abs(amt).toLocaleString()}</strong>`;
    document.getElementById('adjHistory').prepend(li);
    document.getElementById('adjAmt').value = '';
    document.getElementById('adjDesc').value = '';
}

// ==================== REPORT MODAL ====================
function openReport() { openReportFor(currentProjectId); }

function openReportFor(projectId) {
    currentProjectId = projectId;
    const proj = appState.projects.find(p => p.id === projectId);
    if (!proj) return;

    const totalCost = proj.costLabor + proj.costMaterials;
    const profit = proj.budget - totalCost;
    const pct = proj.budget > 0 ? ((profit / proj.budget) * 100).toFixed(1) : 0;

    document.getElementById('reportProjName').textContent = proj.name;
    if (document.getElementById('rep-budget')) {
        document.getElementById('rep-budget').textContent = `€${proj.budget.toLocaleString()}`;
        document.getElementById('rep-cost').textContent = `€${totalCost.toLocaleString()}`;
        document.getElementById('rep-profit').textContent = `${profit >= 0 ? '+' : ''}€${profit.toLocaleString()}`;
        document.getElementById('rep-pct').textContent = `${profit >= 0 ? '+' : ''}${pct}%`;
    }

    // Staff table
    const tbody = document.getElementById('staffTableBody');
    if (tbody) {
        const roleLabel = {
            administrator: 'Διαχειριστής', supervisor: 'Υπεύθυνος', helper: 'Βοηθός',
            admin: 'Admin', foreman: 'Επιβλέπων'
        };
        tbody.innerHTML = appState.employees
            .filter(e => e.hours > 0)
            .map(e => `<tr>
                <td>${e.name}</td>
                <td>${roleLabel[e.role] || e.role}</td>
                <td>${e.hours}h</td>
                <td>€${(e.rate * e.hours).toFixed(2)}</td>
            </tr>`).join('') ||
            '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">Δεν υπάρχουν εγγραφές ωρών.</td></tr>';
    }

    document.getElementById('reportModal').style.display = 'block';

    setTimeout(() => {
        const pieCtx = document.getElementById('costPieChart');
        const barCtx = document.getElementById('laborBarChart');
        if (pieCtx) {
            new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: ['Υλικά', 'Εργατοώρες'],
                    datasets: [{ data: [proj.costMaterials, proj.costLabor], backgroundColor: ['#f59e0b', '#3b82f6'] }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }
        if (barCtx) {
            const withHours = appState.employees.filter(e => e.hours > 0);
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: withHours.map(e => e.name.split(' ')[0]),
                    datasets: [{ label: 'Ώρες', data: withHours.map(e => e.hours), backgroundColor: '#3b82f6', borderRadius: 6 }]
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }
    }, 100);
}

function closeReport() { document.getElementById('reportModal').style.display = 'none'; }

// ==================== LOGOUT ====================
function handleLogout() {
    if (confirm('Αποσύνδεση;')) {
        window.location.href = '/Backend/logout.php';
    }
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
