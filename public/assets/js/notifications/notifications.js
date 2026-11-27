// notifications.js - navbar notification routing and notification-form feedback.
function initializeNotificationPage() {
  const bell = document.getElementById('notifBell');
  const badge = document.getElementById('notifCount');
  const path = new URLSearchParams(window.location.search).get('route') || window.location.pathname;
  const notificationRoutes = {
    admin: '/views/admin/notifications/index/index.php',
    'house-master': '/views/house-master/notifications/index/index.php',
    'senior-houseparent': '/views/senior-houseparent/notifications/index/index.php',
    nurse: '/views/nurse/notifications/notifications.php',
    security: '/views/security/notifications/notifications.php',
    student: '/views/student/notifications/index.php'
  };
  const roleMatch = path.match(/\/views\/(admin|house-master|senior-houseparent|nurse|security|student)\//);
  const notificationRoute = notificationRoutes[roleMatch ? roleMatch[1] : 'admin'];
  const notificationUrl = new URL(window.location.href);
  notificationUrl.searchParams.set('route', notificationRoute);

  async function refreshCount() {
    if (!badge || !path.startsWith('/views/admin/') || document.visibilityState === 'hidden') return;

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
    if (bell.dataset.sdsNotificationBound !== 'true') {
      bell.dataset.sdsNotificationBound = 'true';
      bell.addEventListener('click', function () {
        SDS.showButtonLoading(this);
        window.location.href = notificationUrl.href;
      });
    }
  }

  if (window.SDS.notificationCountInterval) {
    window.clearInterval(window.SDS.notificationCountInterval);
    window.SDS.notificationCountInterval = null;
  }
  if (badge) {
    refreshCount();
    window.SDS.notificationCountInterval = setInterval(refreshCount, 60000);
  }

  const sendForm = document.querySelector('form[action*="notifications"]');
  if (sendForm && sendForm.querySelector('textarea[name="message"]')
    && sendForm.dataset.sdsNotificationBound !== 'true') {
    sendForm.dataset.sdsNotificationBound = 'true';
    sendForm.addEventListener('submit', function () {
      SDS.toast('info', 'Sending notification...');
    });
  }
}

SDS.onPageLoad(initializeNotificationPage);
SDS.onPageRestore(initializeNotificationPage);
