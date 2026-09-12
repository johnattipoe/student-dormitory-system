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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$firebase = FirebaseService::getInstance();
$user = current_user() ?? [];
$userId = (string) ($user['uid'] ?? $user['id'] ?? '');
$userName = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')))) ?: 'Senior Houseparent';

// Fetch all contacts
$allContacts = $firebase->getCollection('emergency_contacts', [], 500);

if (empty($allContacts)) {
    $seed = [
        ['name' => 'Campus Clinic / Infirmary', 'roleTitle' => 'School Nurse Desk', 'phone' => '+233 24 000 0001', 'email' => 'clinic@dormitory.edu', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Campus Security Main Gate', 'roleTitle' => 'Security Office', 'phone' => '+233 24 000 0002', 'email' => 'security@dormitory.edu', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Local Police Dispatch', 'roleTitle' => 'Emergency Police Command', 'phone' => '191 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'National Fire & Rescue Service', 'roleTitle' => 'Fire Operations', 'phone' => '192 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'District Hospital Emergency Unit', 'roleTitle' => 'Hospital Triage Desk', 'phone' => '+233 20 000 0004', 'email' => 'hospital@district.gov', 'priority' => 'critical', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
        ['name' => 'Facilities & Electrical Maintenance', 'roleTitle' => 'Emergency Repairs & Power', 'phone' => '+233 24 000 0003', 'email' => 'facilities@dormitory.edu', 'priority' => 'high', 'status' => 'active', 'createdAt' => date(DATE_ATOM)],
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

// Fetch emergency incident call logs
$incidents = $firebase->getCollection('emergency_incidents', [], 50);
usort($incidents, static fn($a, $b) => strcmp((string) ($b['triggeredAt'] ?? ''), (string) ($a['triggeredAt'] ?? '')));

// Metrics
$totalContacts = count($contacts);
$criticalContacts = count(array_filter($contacts, fn($c) => ($c['priority'] ?? '') === 'critical'));
$todayDate = date('Y-m-d');
$todayCalls = count(array_filter($incidents, fn($i) => str_starts_with((string)($i['triggeredAt'] ?? ''), $todayDate)));

$pageTitle = 'Emergency Alerts & Contacts';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => url('views/senior-houseparent/emergency-alerts/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <!-- Header & Action Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1 text-danger"><i class="bi bi-shield-exclamation me-2"></i> Emergency Command Center & Contacts</h5>
                <p class="text-muted mb-0">Emergency directory, broadcast dispatch, and incident call logging for dormitory security.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-danger btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/broadcast/broadcast.php') ?>">
                    <i class="bi bi-broadcast me-1"></i> Broadcast Emergency Alert
                </a>
                <a class="btn btn-primary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/contacts/add/add.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i> Add Contact
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/log/create.php') ?>">
                    <i class="bi bi-journal-plus me-1"></i> Log Emergency Call
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i> Export Directory & Logs
                </a>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-danger">
                    <small class="text-muted">Critical First Responders</small>
                    <strong class="fs-2 text-danger my-1"><?= e((string) $criticalContacts) ?></strong>
                    <span class="small text-muted">Direct emergency lines</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total Directory Contacts</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalContacts) ?></strong>
                    <span class="small text-muted">Active emergency services</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-warning">
                    <small class="text-muted">Emergency Calls Logged Today</small>
                    <strong class="fs-2 text-warning my-1"><?= e((string) $todayCalls) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
        </div>

        <!-- Emergency Directory Cards -->
        <h6 class="fw-bold mb-3"><i class="bi bi-telephone-fill me-2 text-danger"></i> Quick-Dial First Responders & Directory</h6>
        <div class="row g-3 mb-4">
            <?php foreach ($contacts as $contact): ?>
                <?php 
                    $priority = $contact['priority'] ?? 'normal';
                    $badgeClass = match($priority) {
                        'critical' => 'bg-danger',
                        'high' => 'bg-warning text-dark',
                        default => 'bg-secondary',
                    };
                    $contactId = (string) ($contact['id'] ?? '');
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card stat-card p-3 h-100 border-start border-4 <?= $priority === 'critical' ? 'border-danger' : ($priority === 'high' ? 'border-warning' : 'border-primary') ?>">
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
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/log/create.php?contactId=' . urlencode($contactId)) ?>" title="Log Emergency Call">
                                <i class="bi bi-journal-plus me-1"></i> Log Call
                            </a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/emergency-alerts/contacts/edit/edit.php?id=' . urlencode($contactId)) ?>" title="Edit Contact">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Incident & Call Log Table -->
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Emergency Dispatch & Call Logs</h6>
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/log/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i> Record New Call
                </a>
            </div>

            <?php if (empty($incidents)): ?>
                <p class="text-muted mb-0">No emergency incidents or calls recorded.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Contact Contacted</th>
                                <th>Call Summary & Incident Notes</th>
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
                                    <td><strong><?= e($inc['contactName'] ?? '—') ?></strong></td>
                                    <td><?= e(mb_strimwidth((string)($inc['notes'] ?? '—'), 0, 80, '...')) ?></td>
                                    <td><?= e($inc['triggeredByName'] ?? 'Staff') ?></td>
                                    <td class="text-end">
                                        <?php if ($incId !== ''): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/log/view.php?id=' . urlencode($incId)) ?>">
                                                <i class="bi bi-eye"></i> View
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
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
