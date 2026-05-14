// dashboards/JS/project_details.js
const PROJECT_ID = Number(document.getElementById('projectDetailsApp')?.dataset.projectId || 0);
let allTimeLogs = [];
let allInvoices = [];
let currentInvSortCol = 'date';
let currentInvSortAsc = false;

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
    allInvoices = data.invoices || [];
    applyInvoiceFilters();
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
        const label = adj.reason || adj.description;
        const by = adj.created_by_name ? ' · ' + adj.created_by_name : '';
        const date = formatDate(adj.created_at || adj.date);
        return `
            <div class="history-card">
                <div>
                    <div class="hc-title">${label}${by}</div>
                    <div class="hc-date">${date}</div>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="hc-amount" style="color:${color};">${prefix}${formatEuro(Math.abs(amt))}</div>
                    <button onclick="deleteAdjustment(${adj.id})" style="background:none; border:none; color:#dc2626; cursor:pointer;" title="Διαγραφή Αναπροσαρμογής">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
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
        tbody.innerHTML = '<tr><td colspan="6" class="pd-empty">Δεν υπάρχουν τιμολόγια έργου.</td></tr>';
        return;
    }
    tbody.innerHTML = invoices.map(inv => {
        const photoUrl = inv.photo_url || inv.photo_path || inv.file_path || inv.image_path;
        const isPdf = photoUrl && /\.pdf$/i.test(photoUrl);
        const finalPhotoUrl = photoUrl ? (photoUrl.startsWith('http') ? photoUrl : '/' + photoUrl) : '';

        let thumbCell;
        if (photoUrl) {
            thumbCell = `<img src="${finalPhotoUrl}" class="pd-invoice-thumb" onclick="openImageViewer(event, '${finalPhotoUrl}', ${isPdf ? 'true' : 'false'})" title="Προβολή">`;
        } else {
            thumbCell = '<div style="width:50px;height:50px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;"><i class="fas fa-image"></i></div>';
        }

        const supplier = (inv.supplier_name || '').replace(/'/g, "\\'");
        const amount = parseFloat(inv.amount);
        const invId = inv.id;

        return `
            <tr>
                <td>${thumbCell}</td>
                <td>${formatDate(inv.date || inv.created_at)}</td>
                <td style="font-weight:600;">${inv.supplier_name || '—'}</td>
                <td>${inv.uploaded_by_name || '—'}</td>
                <td style="color:#ef4444;font-weight:700;">${formatEuro(amount)}</td>
                <td>
                    <div class="inv-actions">
                        ${photoUrl ? `<button class="btn-inv-view" onclick="openImageViewer(event, '${photoUrl}', ${isPdf ? 'true' : 'false'})"><i class="fas fa-eye"></i> Εικόνα</button>` : ''}
                        <button class="btn-inv-edit" onclick="adminEditInvoice(${invId}, '${supplier}', ${amount})"><i class="fas fa-edit"></i> Επεξεργασία</button>
                        <button class="btn-inv-delete" onclick="adminDeleteInvoice(${invId})"><i class="fas fa-trash"></i> Διαγραφή</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// ── Invoice Filtering & Sorting ───────────────────────────────────────
function applyInvoiceFilters() {
    let filtered = [...allInvoices];

    const searchStr = (document.getElementById('invFilterSearch').value || '').toLowerCase();
    const periodStr = document.getElementById('invFilterPeriod').value;

    // Date filtering
    if (periodStr !== 'all') {
        const daysLimit = parseInt(periodStr);
        const cutoff = new Date();
        cutoff.setDate(cutoff.getDate() - daysLimit);

        filtered = filtered.filter(inv => {
            const invDate = new Date(inv.date || inv.created_at);
            return invDate >= cutoff;
        });
    }

    // Search filtering
    if (searchStr) {
        filtered = filtered.filter(inv => {
            const supplier = (inv.supplier_name || '').toLowerCase();
            const user = (inv.uploaded_by_name || '').toLowerCase();
            return supplier.includes(searchStr) || user.includes(searchStr);
        });
    }

    // Apply Sort
    filtered.sort((a, b) => {
        let valA, valB;
        let isNumeric = false;

        if (currentInvSortCol === 'date') {
            valA = new Date(a.date || a.created_at || 0).getTime();
            valB = new Date(b.date || b.created_at || 0).getTime();
            isNumeric = true;
        } else if (currentInvSortCol === 'amount') {
            valA = parseFloat(a.amount || 0);
            valB = parseFloat(b.amount || 0);
            isNumeric = true;
        } else if (currentInvSortCol === 'supplier') {
            valA = (a.supplier_name || '').toLowerCase();
            valB = (b.supplier_name || '').toLowerCase();
        } else if (currentInvSortCol === 'user') {
            valA = (a.uploaded_by_name || '').toLowerCase();
            valB = (b.uploaded_by_name || '').toLowerCase();
        }

        if (isNumeric) {
            return currentInvSortAsc ? valA - valB : valB - valA;
        } else {
            if (valA < valB) return currentInvSortAsc ? -1 : 1;
            if (valA > valB) return currentInvSortAsc ? 1 : -1;
            return 0;
        }
    });

    renderInvoices(filtered);
    updateInvoiceSortUI();
}

function sortInvoices(col) {
    if (currentInvSortCol === col) {
        currentInvSortAsc = !currentInvSortAsc; // toggle
    } else {
        currentInvSortCol = col;
        currentInvSortAsc = (col !== 'date' && col !== 'amount'); // Default asc for text, desc for dates/amount
    }
    applyInvoiceFilters();
}

function updateInvoiceSortUI() {
    // Remove active classes
    const ths = document.querySelectorAll('#tab-invoices th.sortable');
    ths.forEach(th => {
        th.classList.remove('sort-active');
        const i = th.querySelector('.sort-icon');
        if (i) i.className = 'fas fa-sort sort-icon';
    });
    // Add active to current
    const activeTh = document.querySelector(`#tab-invoices th[data-sort="${currentInvSortCol}"]`);
    if (activeTh) {
        activeTh.classList.add('sort-active');
        const i = activeTh.querySelector('.sort-icon');
        if (i) {
            i.className = currentInvSortAsc ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
        }
    }
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

// ── CSRF helper ───────────────────────────────────────────────────────
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ── Invoice Admin Actions ─────────────────────────────────────────────
function adminEditInvoice(id, supplier, amount) {
    document.getElementById('editInvId').value = id;
    document.getElementById('editInvSupplier').value = supplier;
    document.getElementById('editInvAmount').value = amount;
    document.getElementById('editInvoiceModal').classList.add('show');
}

function closeEditInvoiceModal() {
    document.getElementById('editInvoiceModal').classList.remove('show');
    document.getElementById('edit-invoice-msg').className = 'form-msg';
}

async function submitEditInvoice(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('editInvId').value);
    const supplier = document.getElementById('editInvSupplier').value.trim();
    const amount = parseFloat(document.getElementById('editInvAmount').value);
    const btn = document.getElementById('editInvSubmitBtn');

    if (!supplier || !amount || amount <= 0) {
        showMsg('edit-invoice-msg', 'Συμπληρώστε προμηθευτή και έγκυρο ποσό.', true);
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Αποθήκευση…';

    try {
        const res = await fetch('actions/admin_edit_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, vendor: supplier, amount, csrf_token: getCsrf() })
        });
        const data = await res.json();
        if (data.success) {
            closeEditInvoiceModal();
            await refreshData();
        } else {
            showMsg('edit-invoice-msg', data.message || 'Σφάλμα.', true);
        }
    } catch {
        showMsg('edit-invoice-msg', 'Σφάλμα σύνδεσης.', true);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Αποθήκευση';
    }
}

async function deleteAdjustment(id) {
    const result = await Swal.fire({
        title: 'Διαγραφή Αναπροσαρμογής;',
        text: 'Η ενέργεια αυτή δεν μπορεί να αναιρεθεί και θα επηρεάσει τον συνολικό προϋπολογισμό.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ναι, διαγραφή',
        cancelButtonText: 'Ακύρωση'
    });
    if (!result.isConfirmed) return;

    try {
        const res = await fetch('../Backend/ProjectDetails/delete_budget_adjustment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Διαγράφηκε!', timer: 1500, showConfirmButton: false });
            await refreshData();
        } else {
            Swal.fire({ icon: 'error', title: 'Σφάλμα', text: data.message || 'Αποτυχία διαγραφής.' });
        }
    } catch {
        Swal.fire({ icon: 'error', title: 'Σφάλμα', text: 'Σφάλμα σύνδεσης.' });
    }
}

async function adminDeleteInvoice(id) {
    const result = await Swal.fire({
        title: 'Διαγραφή τιμολογίου;',
        text: 'Η ενέργεια αυτή δεν μπορεί να αναιρεθεί.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ναι, διαγραφή',
        cancelButtonText: 'Ακύρωση'
    });
    if (!result.isConfirmed) return;

    try {
        const res = await fetch('actions/admin_delete_invoice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, csrf_token: getCsrf() })
        });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Διαγράφηκε!', timer: 1500, showConfirmButton: false });
            await refreshData();
        } else {
            Swal.fire({ icon: 'error', title: 'Σφάλμα', text: data.message || 'Αποτυχία διαγραφής.' });
        }
    } catch {
        Swal.fire({ icon: 'error', title: 'Σφάλμα', text: 'Σφάλμα σύνδεσης.' });
    }
}

// ── Image Viewer Modal ────────────────────────────────────────────────
function openImageViewer(event, photoUrl, isPdf) {
    event.stopPropagation();
    const url = photoUrl.startsWith('http') ? photoUrl : '/' + photoUrl;
    if (isPdf) {
        window.open(url, '_blank');
        return;
    }
    const overlay = document.getElementById('imgViewerOverlay');
    document.getElementById('imgViewerImg').src = url;
    overlay.classList.add('show');
}

function closeImageViewer() {
    document.getElementById('imgViewerOverlay').classList.remove('show');
    document.getElementById('imgViewerImg').src = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('imgViewerOverlay').classList.remove('show');
        document.getElementById('imgViewerImg').src = '';
        closeEditInvoiceModal();
    }
});

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
