<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\HouseService;

$firebase = FirebaseService::getInstance();
$roleTitle = current_role() === ROLE_HOUSE_MISTRESS ? 'House Mistress' : 'House Master';
$userId = (string) ($user['uid'] ?? $user['id'] ?? '');
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: $roleTitle;
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

// Fetch contacts
$allContacts = $firebase->getCollection('emergency_contacts', [], 500);

if (empty($allContacts)) {
    $seed = [
        ['name' => 'Campus Clinic / Infirmary', 'roleTitle' => 'School Nurse Desk', 'phone' => '+233 24 000 0001', 'email' => 'clinic@dormitory.edu', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Campus Security Main Gate', 'roleTitle' => 'Security Office', 'phone' => '+233 24 000 0002', 'email' => 'security@dormitory.edu', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Local Police Dispatch', 'roleTitle' => 'Emergency Police Command', 'phone' => '191 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'National Fire & Rescue Service', 'roleTitle' => 'Fire Operations', 'phone' => '192 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Facilities & Maintenance', 'roleTitle' => 'Emergency Repairs', 'phone' => '+233 24 000 0003', 'email' => 'facilities@dormitory.edu', 'priority' => 'high', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
    ];
    foreach ($seed as $c) {
        $firebase->addDocument('emergency_contacts', $c);
    }
    $allContacts = $firebase->getCollection('emergency_contacts', [], 500);
}

$contacts = array_values(array_filter($allContacts, static fn($c) => ($c['status'] ?? 'active') === 'active'));

usort($contacts, static function ($a, $b) {
    $weights = ['critical' => 0, 'high' => 1, 'normal' => 2];
    $wA = $weights[$a['priority'] ?? 'normal'] ?? 3;
    $wB = $weights[$b['priority'] ?? 'normal'] ?? 3;
    return $wA <=> $wB;
});

// Fetch emergency incidents scoped to house or general
$allIncidents = $firebase->getCollection('emergency_incidents', [], 50);
$incidents = array_values(array_filter($allIncidents, function ($inc) use ($houseId, $userId) {
    if (!empty($inc['houseId']) && (string)$inc['houseId'] === $houseId) return true;
    if (!empty($inc['triggeredBy']) && (string)$inc['triggeredBy'] === $userId) return true;
    if (!empty($inc['isBroadcast'])) return true;
    return false;
}));

usort($incidents, static fn($a, $b) => strcmp((string) ($b['triggeredAt'] ?? ''), (string) ($a['triggeredAt'] ?? '')));

$totalContacts = count($contacts);
$criticalContacts = count(array_filter($contacts, fn($c) => ($c['priority'] ?? '') === 'critical'));
$todayDate = date('Y-m-d');
$todayCalls = count(array_filter($incidents, fn($i) => str_starts_with((string)($i['triggeredAt'] ?? ''), $todayDate)));

$pageTitle = 'Emergency Alerts & Contacts';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => url('views/house-master/emergency-alerts/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-shield-exclamation text-danger me-2"></i>Emergency Contacts & Protocols</h4>
                <p class="text-muted mb-0"><?= e($houseName) ?> — Quick-dial directory and incident call logging</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-danger btn-sm" href="<?= url('views/house-master/emergency-alerts/broadcast/broadcast.php') ?>">
                    <i class="bi bi-broadcast me-1"></i>Broadcast Alert
                </a>
                <a class="btn btn-primary btn-sm" href="<?= url('views/house-master/emergency-alerts/contacts/add/add.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>Add Contact
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/emergency-alerts/log/create.php') ?>">
                    <i class="bi bi-journal-plus me-1"></i>Log Call
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/emergency-alerts/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i>Export
                </a>
            </div>
        </div>

        <!-- KPI Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Critical Responders</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $criticalContacts) ?></h3>
                            <span class="small text-muted">Direct emergency lines</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-telephone-fill fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Directory Contacts</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalContacts) ?></h3>
                            <span class="small text-muted">Active responder services</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Calls Today</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $todayCalls) ?></h3>
                            <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-clock-history fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Directory Cards -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-telephone-fill me-2 text-danger"></i>Quick-Dial First Responders</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($contacts as $contact): ?>
                        <?php 
                            $priority = $contact['priority'] ?? 'normal';
                            $badgeClass = match($priority) {
                                'critical' => 'bg-danger',
                                'high' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            $borderColor = match($priority) {
                                'critical' => 'border-danger',
                                'high' => 'border-warning',
                                default => 'border-primary',
                            };
                            $contactId = (string) ($contact['id'] ?? '');
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card stat-card p-3 h-100 border-start border-4 <?= $borderColor ?> shadow-sm">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-0"><?= e($contact['name'] ?? 'Contact') ?></h6>
                                        <small class="text-muted"><?= e($contact['roleTitle'] ?? 'Emergency Service') ?></small>
                                    </div>
                                    <span class="badge <?= e($badgeClass) ?>"><?= e(ucfirst($priority)) ?></span>
                                </div>
                                <div class="my-2">
                                    <a href="tel:<?= e($contact['phone'] ?? '') ?>" class="btn btn-sm btn-outline-danger w-100 fw-bold fs-6">
                                        <i class="bi bi-telephone-fill me-1"></i> <?= e($contact['phone'] ?? '—') ?>
                                    </a>
                                </div>
                                <?php if (!empty($contact['email'])): ?>
                                    <div class="small text-muted mb-2 text-truncate">
                                        <i class="bi bi-envelope me-1"></i> <?= e($contact['email']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/emergency-alerts/log/create.php?contactId=' . urlencode($contactId)) ?>" title="Log Emergency Call">
                                        <i class="bi bi-journal-plus me-1"></i>Log Call
                                    </a>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/emergency-alerts/contacts/edit/edit.php?id=' . urlencode($contactId)) ?>" title="Edit Contact">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Logs Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Emergency Call Logs (<?= e($houseName) ?>)</h6>
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/house-master/emergency-alerts/log/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>Record New Call
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($incidents)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No emergency incidents or calls recorded for your house.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Contact</th>
                                    <th>Summary & Notes</th>
                                    <th>Logged By</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $inc): ?>
                                    <?php $incId = (string) ($inc['id'] ?? ''); ?>
                                    <tr>
                                        <td class="text-nowrap small text-muted">
                                            <?= !empty($inc['triggeredAt']) ? e(date('M d, Y H:i', strtotime((string)$inc['triggeredAt']))) : '—' ?>
                                        </td>
                                        <td class="fw-medium"><?= e($inc['contactName'] ?? '—') ?></td>
                                        <td class="small"><?= e(mb_strimwidth((string)($inc['notes'] ?? '—'), 0, 80, '...')) ?></td>
                                        <td><?= e($inc['triggeredByName'] ?? 'Staff') ?></td>
                                        <td class="text-end">
                                            <?php if ($incId !== ''): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/emergency-alerts/log/view.php?id=' . urlencode($incId)) ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>