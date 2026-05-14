// dashboards/JS/admin_bootstrap.js
const adminDataElement = document.getElementById('admin-dashboard-data');
if (adminDataElement) {
  try {
    const data = JSON.parse(adminDataElement.textContent || '{}');
    window.__DB_EMPLOYEES__ = data.employees || [];
    window.__DB_USERS_FULL__ = data.usersFull || [];
    window.__DB_PROJECTS__ = data.projects || [];
    window.__DB_OVERTIME__ = data.overtime || [];
    window.__DB_INVOICES__ = data.invoices || [];
  } catch (err) {
    console.error('Failed to parse admin dashboard data', err);
    window.__DB_EMPLOYEES__ = [];
    window.__DB_USERS_FULL__ = [];
    window.__DB_PROJECTS__ = [];
    window.__DB_OVERTIME__ = [];
    window.__DB_INVOICES__ = [];
  }
}