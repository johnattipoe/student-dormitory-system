<?php
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

$alerts = [];
if (is_array($flash)) {
    if (isset($flash['type'], $flash['message'])) {
        $alerts[] = ['type' => $flash['type'], 'message' => $flash['message']];
    } else {
        foreach ($flash as $type => $message) {
            if ($message !== null && $message !== '') {
                $alerts[] = ['type' => $type, 'message' => $message];
            }
        }
    }
}
?>
<?php foreach ($alerts as $alert): ?>
<div class="alert alert-<?= e($alert['type'] === 'error' ? 'danger' : $alert['type']) ?> alert-dismissible fade show m-3" role="alert">
    <?= e($alert['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>
