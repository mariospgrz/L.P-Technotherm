// dashboards/JS/supervisor_bootstrap.js
const supervisorDataElement = document.getElementById('supervisor-dashboard-data');
if (supervisorDataElement) {
  try {
    const data = JSON.parse(supervisorDataElement.textContent || '{}');
    window.__PROJECTS__ = data.projects || [];
    window.__INVOICES__ = data.invoices || [];
    window.__HELPERS__ = data.helpers || [];
    window.__OVERTIME__ = data.overtime || [];
    window.__ASSIGNMENTS__ = data.assignments || [];
    window.__WORK_LOGS__ = data.workLogs || [];
  } catch (err) {
    console.error('Failed to parse supervisor dashboard data', err);
    window.__PROJECTS__ = [];
    window.__INVOICES__ = [];
    window.__HELPERS__ = [];
    window.__OVERTIME__ = [];
    window.__ASSIGNMENTS__ = [];
    window.__WORK_LOGS__ = [];
  }
}