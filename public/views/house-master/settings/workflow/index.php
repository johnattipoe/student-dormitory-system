<?php
if (!defined('APP_ROOT')) require dirname(__DIR__, 5) . '/public/bootstrap.php';
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';
use App\Services\FirebaseService;
$userId = current_user_id();
$firebase = FirebaseService::getInstance();
$settingsDoc = $firebase->getDocument('user_settings', $userId) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = ['defaultReportPeriod' => sanitize($_POST['defaultReportPeriod'] ?? 'today')];
    if (!in_array($newSettings['defaultReportPeriod'], ['today', 'week', 'month'], true)) $newSettings['defaultReportPeriod'] = 'today';
    try {
        if (!empty($settingsDoc)) $firebase->updateDocument('user_settings', $userId, $newSettings);
        else $firebase->addDocument('user_settings', $newSettings, $userId);
        $settingsDoc = array_merge($settingsDoc, $newSettings);
        flash('success', 'Workflow preferences updated.');
    } catch (Throwable $e) { flash('error', 'Failed to save workflow preferences: ' . $e->getMessage()); }
}
$pageTitle = 'Workflow Settings';
$navItems = [['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')], ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/house-master/settings/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content"><div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"><div><h5 class="mb-1"><i class="bi bi-diagram-3 me-2 text-primary"></i>Workflow Settings</h5><p class="text-muted mb-0">Set defaults for your supervisory reporting workflow.</p></div><a href="<?= url('views/house-master/settings/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Settings</a></div>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="card stat-card p-4" style="max-width: 800px;"><form method="POST"><label class="form-label fw-bold" for="defaultReportPeriod">Default report period</label><select class="form-select" id="defaultReportPeriod" name="defaultReportPeriod"><option value="today" <?= ($settingsDoc['defaultReportPeriod'] ?? 'today') === 'today' ? 'selected' : '' ?>>Today</option><option value="week" <?= ($settingsDoc['defaultReportPeriod'] ?? '') === 'week' ? 'selected' : '' ?>>This week</option><option value="month" <?= ($settingsDoc['defaultReportPeriod'] ?? '') === 'month' ? 'selected' : '' ?>>This month</option></select><div class="border-top pt-3 mt-4 text-end"><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Workflow</button></div></form></div>
</div></div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
