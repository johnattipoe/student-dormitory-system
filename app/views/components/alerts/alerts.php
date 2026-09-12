<?php
/** Flash alerts component with auto-dismiss support
 * Supports: $_SESSION['_flash'] as array or individual message
 * Params: $autoDismiss (ms, 0 = manual), $position (top|bottom)
 */
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);
$autoDismiss = (int) ($autoDismiss ?? 5000);
$position = $position ?? 'top';

$alerts = [];
if (is_array($flash)) {
    if (isset($flash['type'], $flash['message'])) {
        $alerts[] = ['type' => $flash['type'], 'message' => $flash['message'], 'icon' => $flash['icon'] ?? null];
    } else {
        foreach ($flash as $type => $message) {
            if ($message !== null && $message !== '') {
                $alerts[] = ['type' => $type, 'message' => $message, 'icon' => null];
            }
        }
    }
}

$icons = [
    'success' => '✓',
    'danger' => '✕',
    'error' => '✕',
    'warning' => '⚠',
    'info' => 'ℹ'
];
?>
<?php if (!empty($alerts)): ?>
<div class="alerts-container position-fixed <?= $position === 'bottom' ? 'bottom' : 'top' ?>-0 start-50 translate-middle-x" style="z-index: 9998;">
    <?php foreach ($alerts as $index => $alert): ?>
        <?php $type = $alert['type'] === 'error' ? 'danger' : $alert['type']; ?>
        <div class="alert alert-<?= $type ?> alert-dismissible fade show m-3 alert-animated" role="alert" data-autohide="<?= $autoDismiss ?>" data-index="<?= $index ?>">
            <span class="alert-icon me-2"><?= $icons[$type] ?? '' ?></span>
            <span class="alert-message"><?= e($alert['message']) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
    .alerts-container { display: flex; flex-direction: column; gap: 0.5rem; }
    .alert-animated { animation: slideInDown 0.3s ease-out; }
    .alert-icon { font-weight: bold; }
    .alert-message { flex: 1; }
    @keyframes slideInDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @media (max-width: 576px) { .alert { margin: 0.5rem !important; } }
</style>

<script>
(function() {
    document.querySelectorAll('[data-autohide]').forEach(alert => {
        const ms = parseInt(alert.getAttribute('data-autohide')) || 0;
        if (ms > 0) {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, ms);
        }
    });
})();
</script>
