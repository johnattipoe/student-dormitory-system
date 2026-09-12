<?php
/** Confirm action modal with form method override support
 * Params: $modalId (default: 'confirmModal'), $title (default: 'Confirm Action'), $actionLabel (default: 'Confirm')
 * Usage: data-toggle-modal="#confirmModal" data-action-url="/path" data-method="DELETE" data-confirm-text="Custom message"
 */
$modalId = $modalId ?? 'confirmModal';
$title = $title ?? 'Confirm Action';
$actionLabel = $actionLabel ?? 'Confirm';
$actionButtonClass = $actionButtonClass ?? 'btn-danger';
?>
<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-labelledby="<?= e($modalId) ?>Title" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title" id="<?= e($modalId) ?>Title"><?= e($title) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="<?= e($modalId) ?>Body">Are you sure?</div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" id="<?= e($modalId) ?>Form" style="display: inline;">
          <input type="hidden" name="_method" id="<?= e($modalId) ?>Method" value="POST">
          <button type="submit" class="btn <?= $actionButtonClass ?>" id="<?= e($modalId) ?>Btn"><?= e($actionLabel) ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    const modalId = '<?= $modalId ?>';
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    // Intercept confirm buttons with data attributes
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-toggle-modal="#' + modalId + '"]');
        if (!btn) return;
        
        e.preventDefault();
        const form = modal.querySelector('form');
        const titleEl = modal.querySelector('[id$="Title"]');
        const bodyEl = modal.querySelector('[id$="Body"]');
        const confirmBtn = modal.querySelector('[id$="Btn"]');
        const methodInput = modal.querySelector('[name="_method"]');
        
        // Update modal content from data attributes
        if (btn.dataset.title) titleEl.textContent = btn.dataset.title;
        if (btn.dataset.confirmText) bodyEl.textContent = btn.dataset.confirmText;
        if (btn.dataset.confirmLabel) confirmBtn.textContent = btn.dataset.confirmLabel;
        if (btn.dataset.method) methodInput.value = btn.dataset.method.toUpperCase();
        if (btn.dataset.actionClass) {
            confirmBtn.className = 'btn ' + btn.dataset.actionClass;
        }
        
        // Set form action
        if (btn.dataset.actionUrl) {
            form.action = btn.dataset.actionUrl;
        } else if (btn.closest('form')) {
            form.action = btn.closest('form').action;
        }
        
        // Show modal
        new bootstrap.Modal(modal).show();
    });
})();
</script>
