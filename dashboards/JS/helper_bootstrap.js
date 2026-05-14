// dashboards/JS/helper_bootstrap.js
const helperDataElement = document.getElementById('helper-dashboard-data');
if (helperDataElement) {
  try {
    const data = JSON.parse(helperDataElement.textContent || '{}');
    window.__PROJECTS__ = data.projects || [];
    window.__WORK_LOGS__ = data.workLogs || [];
    window.__OVERTIME__ = data.overtime || [];
  } catch (err) {
    console.error('Failed to parse helper dashboard data', err);
    window.__PROJECTS__ = [];
    window.__WORK_LOGS__ = [];
    window.__OVERTIME__ = [];
  }
}