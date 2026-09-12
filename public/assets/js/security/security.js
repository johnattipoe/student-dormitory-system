// security.js — security dashboard/check-in-out helpers.
document.addEventListener('DOMContentLoaded', function () {
  // Auto-refresh the "on premises" count on the security dashboard every 60s
  // by re-fetching the notifications-count-style endpoint pattern is overkill here;
  // instead we just refresh the whole on-premises table section periodically.
  const onPremTable = document.getElementById('onPremisesTable');
  if (onPremTable) {
    setInterval(() => {
      fetch(window.location.href, { credentials: 'same-origin' })
        .then(r => r.text())
        .then(html => {
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const fresh = doc.getElementById('onPremisesTable');
          if (fresh) onPremTable.innerHTML = fresh.innerHTML;
        })
        .catch(() => {});
    }, 60000);
  }

  // Check-in / check-out select2 dropdowns: focus immediately for fast scanning workflows
  const firstSelect = document.querySelector('select.select2');
  if (firstSelect && window.jQuery) {
    setTimeout(() => jQuery(firstSelect).select2('open'), 300);
  }
});
