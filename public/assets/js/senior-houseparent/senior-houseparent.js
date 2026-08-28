// houseparent.js — progressively enhances the Visitor Requests approve/reject
// buttons to remove the row on success instead of a full page reload.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form').forEach(form => {
    const hasDecisionButtons = form.querySelector('button[name="decision"]');
    if (!hasDecisionButtons) return;

    form.querySelectorAll('button[name="decision"]').forEach(btn => {
      btn.addEventListener('click', function (e) {
        // Let the normal POST happen (simplest, most reliable); just show
        // immediate feedback so the action doesn't feel unresponsive.
        SDS.toast('info', this.value === 'approve' ? 'Approving…' : 'Rejecting…');
      });
    });
  });
});
