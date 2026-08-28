// nurse.js — nurse dashboard helpers: emergency-case count pulse animation.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.stat-card .text-danger').forEach(el => {
    const card = el.closest('.stat-card');
    if (card && parseInt(el.textContent, 10) > 0) {
      card.classList.add('border-danger');
    }
  });
});
