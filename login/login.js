// login/login.js
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const success = params.get('success');
  const error = params.get('error');

  if (success) {
    const el = document.getElementById('alertSuccess');
    el.textContent = decodeURIComponent(success);
    el.style.display = 'block';
  }

  if (error) {
    const el = document.getElementById('alertError');
    el.textContent = decodeURIComponent(error);
    el.style.display = 'block';
  }
});