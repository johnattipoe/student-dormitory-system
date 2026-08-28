// student.js — self-service pages: mostly relies on shared app.js behavior.
// Adds a "read" state fade on notifications the student views on their dashboard.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.border-bottom.py-2').forEach(el => {
    el.addEventListener('click', () => el.classList.add('opacity-75'));
  });
});
