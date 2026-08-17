// notifications.js - navbar notification routing and notification-form feedback.
document.addEventListener('DOMContentLoaded', function () {
  const bell = document.getElementById('notifBell');
  const badge = document.getElementById('notifCount');
  const path = window.location.pathname;
  const notificationRoutes = {
    admin: '/views/admin/notifications/index.php',
    'house-master': '/views/house-master/notifications/index.php',
    houseparent: '/views/houseparent/notifications/index.php',
    nurse: '/views/nurse/notifications/notifications.php',
    security: '/views/security/notifications/notifications.php',
    student: '/views/student/notifications/index.php'
  };
  const roleMatch = path.match(/\/views\/(admin|house-master|houseparent|nurse|security|student)\//);
  const notificationUrl = roleMatch ? notificationRoutes[roleMatch[1]] : '/views/admin/notifications/index.php';

  async function refreshCount() {
    if (!badge || !path.startsWith('/views/admin/')) return;

    try {
      const res = await SDS.getJSON('/views/admin/notifications-count.php');
      const count = (res && ((res.data && res.data.count) || res.count)) || 0;
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
      } else {
        badge.style.display = 'none';
      }
    } catch (e) {
      // Silent fail: the badge will update on the next successful poll.
    }
  }

  if (bell) {
    bell.addEventListener('click', function () {
      window.location.href = notificationUrl;
    });
  }

  if (badge) {
    refreshCount();
    setInterval(refreshCount, 30000);
  }

  const sendForm = document.querySelector('form[action*="notifications"]');
  if (sendForm && sendForm.querySelector('textarea[name="message"]')) {
    sendForm.addEventListener('submit', function () {
      SDS.toast('info', 'Sending notification...');
    });
  }
});
