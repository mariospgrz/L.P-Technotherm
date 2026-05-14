// frontend/change_password.js
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');
  const error = params.get('error');

  if (!token) {
    const el = document.getElementById('alertError');
    el.textContent = 'Μη έγκυρος ή ληγμένος σύνδεσμος. Παρακαλώ ζητήστε νέον.';
    el.style.display = 'block';
    document.getElementById('resetForm').style.display = 'none';
  } else {
    document.getElementById('token').value = token;
  }

  if (error) {
    const el = document.getElementById('alertError');
    el.textContent = decodeURIComponent(error);
    el.style.display = 'block';
  }

  const passwordInput = document.getElementById('password');
  const strengthBar = document.getElementById('strengthBar');
  const strengthLabel = document.getElementById('strengthLabel');

  passwordInput.addEventListener('input', () => {
    const val = passwordInput.value;
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
      { label: '', color: 'transparent', width: '0%' },
      { label: 'Αδύναμος', color: '#ef4444', width: '25%' },
      { label: 'Μέτριος', color: '#f97316', width: '50%' },
      { label: 'Καλός', color: '#eab308', width: '75%' },
      { label: 'Ισχυρός', color: '#22c55e', width: '100%' },
    ];

    strengthBar.style.width = levels[score].width;
    strengthBar.style.backgroundColor = levels[score].color;
    strengthLabel.textContent = levels[score].label;
  });
});