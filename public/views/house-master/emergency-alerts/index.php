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
use App\Services\NotificationService;
use App\Services\StudentService;

$firebase = FirebaseService::getInstance();
$user = current_user() ?? [];
$userId = (string) current_user_id();
$userName = trim((string) ($user['name'] ?? $user['fullName'] ?? '')) ?: 'House staff';
$roleTitle = current_role() === ROLE_HOUSE_MISTRESS ? 'House Mistress' : 'House Master';
$houseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');
$students = $houseId !== '' ? StudentService::allRecords($houseId) : [];
$indexUrl = url('views/house-master/emergency-alerts/index.php');
$exportUrl = url('views/house-master/emergency-alerts/export/export.php');

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

$contacts = array_values(array_filter($allContacts, static function ($contact) use ($houseId): bool {
    $contactHouseId = (string) ($contact['houseId'] ?? '');
    return ($contact['status'] ?? 'active') === 'active'
        && ($contactHouseId === '' || ($houseId !== '' && $contactHouseId === $houseId));
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $broadcastRecorded = false;
    try {
        if ($action === 'add_contact') {
            if ($houseId === '') {
                throw new \InvalidArgumentException('Your account must have an assigned house to add a house contact.');
            }
            $name = trim((string) sanitize($_POST['name'] ?? ''));
            $phone = trim((string) sanitize($_POST['phone'] ?? ''));
            $priority = (string) ($_POST['priority'] ?? 'normal');
            if ($name === '' || $phone === '' || !in_array($priority, ['critical', 'high', 'normal'], true)) {
                throw new \InvalidArgumentException('Enter a contact name, phone number, and valid priority.');
            }
            $firebase->addDocument('emergency_contacts', [
                'name' => $name,
                'roleTitle' => trim((string) sanitize($_POST['roleTitle'] ?? '')),
                'phone' => $phone,
                'altPhone' => trim((string) sanitize($_POST['altPhone'] ?? '')),
                'email' => trim((string) sanitize($_POST['email'] ?? '')),
                'location' => trim((string) sanitize($_POST['location'] ?? '')),
                'priority' => $priority,
                'status' => 'active',
                'houseId' => $houseId,
                'addedBy' => $userId,
            ]);
            flash('success', 'Emergency contact added to ' . $houseName . '.');
        } elseif ($action === 'update_contact') {
            $contactId = trim((string) sanitize($_POST['id'] ?? ''));
            $contact = $contactId !== '' ? $firebase->getDocument('emergency_contacts', $contactId) : null;
            if (!$contact || $houseId === '' || (string) ($contact['houseId'] ?? '') !== $houseId) {
                throw new \InvalidArgumentException('You can only edit emergency contacts assigned to your house.');
            }
            $name = trim((string) sanitize($_POST['name'] ?? ''));
            $phone = trim((string) sanitize($_POST['phone'] ?? ''));
            $priority = (string) ($_POST['priority'] ?? 'normal');
            if ($name === '' || $phone === '' || !in_array($priority, ['critical', 'high', 'normal'], true)) {
                throw new \InvalidArgumentException('Enter a contact name, phone number, and valid priority.');
            }
            $firebase->updateDocument('emergency_contacts', $contactId, [
                'name' => $name,
                'roleTitle' => trim((string) sanitize($_POST['roleTitle'] ?? '')),
                'phone' => $phone,
                'altPhone' => trim((string) sanitize($_POST['altPhone'] ?? '')),
                'email' => trim((string) sanitize($_POST['email'] ?? '')),
                'location' => trim((string) sanitize($_POST['location'] ?? '')),
                'priority' => $priority,
                'updatedAt' => date(DATE_ATOM),
            ]);
            flash('success', 'Emergency contact updated.');
        } elseif ($action === 'create_log') {
            if ($houseId === '') {
                throw new \InvalidArgumentException('Your account must have an assigned house to log an incident.');
            }
            $contactName = trim((string) sanitize($_POST['contactName'] ?? ''));
            $notes = trim((string) sanitize($_POST['notes'] ?? ''));
            if ($contactName === '' || $notes === '') {
                throw new \InvalidArgumentException('Contact name and call summary are required.');
            }
            $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
            $studentName = '';
            $studentHouseId = '';
            if ($studentId !== '') {
                foreach ($students as $student) {
                    if ((string) ($student['id'] ?? '') === $studentId) {
                        $studentName = trim((string) ($student['firstName'] ?? '') . ' ' . (string) ($student['lastName'] ?? ''));
                        $studentHouseId = (string) ($student['houseId'] ?? '');
                        break;
                    }
                }
                if ($studentName === '' || $studentHouseId !== $houseId) {
                    throw new \InvalidArgumentException('Select a student assigned to your house.');
                }
            }
            $firebase->addDocument('emergency_incidents', array_filter([
                'contactName' => $contactName,
                'contactPhone' => trim((string) sanitize($_POST['contactPhone'] ?? '')),
                'reason' => trim((string) sanitize($_POST['reason'] ?? '')),
                'notes' => $notes,
                'actionTaken' => trim((string) sanitize($_POST['actionTaken'] ?? '')),
                'studentId' => $studentId,
                'studentName' => $studentName,
                'houseId' => $houseId,
                'triggeredBy' => $userId,
                'triggeredByName' => $userName . ' (' . $roleTitle . ')',
                'triggeredAt' => date(DATE_ATOM),
            ], static fn($value) => $value !== ''));
            flash('success', 'Emergency call record logged successfully.');
        } elseif ($action === 'broadcast') {
            if ($houseId === '') {
                throw new \InvalidArgumentException('Your account must have an assigned house to broadcast an alert.');
            }
            $title = trim((string) sanitize($_POST['title'] ?? ''));
            $message = trim((string) sanitize($_POST['message'] ?? ''));
            $emergencyType = (string) ($_POST['emergencyType'] ?? 'security');
            $severity = (string) ($_POST['severity'] ?? 'critical');
            if ($title === '' || $message === '') {
                throw new \InvalidArgumentException('Alert title and message are required.');
            }
            if (!in_array($emergencyType, ['fire', 'medical', 'security', 'weather', 'power'], true)
                || !in_array($severity, ['critical', 'high'], true)) {
                throw new \InvalidArgumentException('Select a valid emergency category and severity.');
            }
            $now = date(DATE_ATOM);
            $firebase->addDocument('emergency_incidents', [
                'title' => $title,
                'notes' => $message,
                'emergencyType' => $emergencyType,
                'severity' => $severity,
                'audience' => 'house',
                'targetHouseId' => $houseId,
                'houseId' => $houseId,
                'isBroadcast' => true,
                'contactName' => 'HOUSE EMERGENCY BROADCAST',
                'triggeredBy' => $userId,
                'triggeredByName' => $userName . ' (' . $roleTitle . ')',
                'triggeredAt' => $now,
            ]);
            $broadcastRecorded = true;
            $firebase->addDocument('announcements', [
                'title' => 'EMERGENCY ALERT: ' . $title,
                'message' => $message,
                'audience' => 'house',
                'targetHouseId' => $houseId,
                'type' => 'danger',
                'status' => 'published',
                'isUrgent' => true,
                'createdBy' => $userId,
                'createdByName' => $userName . ' (' . $roleTitle . ')',
                'publishedAt' => $now,
            ]);
            $notificationService = new NotificationService();
            $recipientCount = 0;
            $recipients = $firebase->getAllCollection('users', [['houseId', '=', $houseId]]);
            foreach ($recipients as $recipient) {
                if ((string) ($recipient['houseId'] ?? '') !== $houseId
                    || ($recipient['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $recipientId = (string) ($recipient['uid'] ?? $recipient['id'] ?? '');
                if ($recipientId === '') {
                    continue;
                }
                $result = $notificationService->create([
                    'recipientType' => 'user',
                    'userId' => $recipientId,
                    'title' => 'EMERGENCY: ' . $title,
                    'message' => $message,
                    'type' => 'danger',
                    'isUrgent' => true,
                ]);
                $recipientCount += !empty($result['success']) ? 1 : 0;
            }
            if ($recipientCount < 1) {
                flash('warning', 'The alert was recorded for ' . $houseName . ', but no notifications were confirmed for house users.');
            } else {
                flash('success', 'Emergency alert sent to ' . $recipientCount . ' user(s) assigned to ' . $houseName . '.');
            }
        } else {
            throw new \InvalidArgumentException('Unknown emergency action.');
        }
    } catch (\Throwable $e) {
        if ($action === 'broadcast' && $broadcastRecorded) {
            flash('warning', 'The house emergency alert was recorded, but a later publishing or notification step failed: ' . $e->getMessage());
        } else {
            flash('error', 'Emergency action failed: ' . $e->getMessage());
        }
    }
    redirect($indexUrl);
}

usort($contacts, static function ($a, $b) {
    $weights = ['critical' => 0, 'high' => 1, 'normal' => 2];
    $wA = $weights[$a['priority'] ?? 'normal'] ?? 3;
    $wB = $weights[$b['priority'] ?? 'normal'] ?? 3;
    return $wA <=> $wB;
});

// Fetch emergency incidents scoped to house or general
$allIncidents = $firebase->getCollection('emergency_incidents', [], 150);
$incidents = array_values(array_filter($allIncidents, function ($inc) use ($houseId, $userId) {
    $incidentHouseId = (string) ($inc['houseId'] ?? '');
    if ($incidentHouseId !== '') return $houseId !== '' && $incidentHouseId === $houseId;
    if (!empty($inc['triggeredBy']) && (string) $inc['triggeredBy'] === $userId) return true;
    if (!empty($inc['isBroadcast']) && in_array(($inc['audience'] ?? ''), ['all', 'house_master'], true)) return true;
    return !empty($inc['isBroadcast'])
        && ($inc['audience'] ?? '') === 'house'
        && (string) ($inc['targetHouseId'] ?? '') === $houseId
        && $houseId !== '';
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
        <?php if ($houseId === ''): ?>
            <div class="alert alert-warning">This account has no assigned house. House-scoped emergency actions are disabled until an administrator assigns one.</div>
        <?php endif; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-shield-exclamation text-danger me-2"></i>Emergency Contacts & Protocols</h4>
                <p class="text-muted mb-0"><?= e($houseName) ?> — Quick-dial directory and incident call logging</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyBroadcastModal" <?= $houseId === '' ? 'disabled title="Assign a house to this account first"' : '' ?>>
                    <i class="bi bi-broadcast me-1"></i>Broadcast Alert
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyContactAddModal" <?= $houseId === '' ? 'disabled title="Assign a house to this account first"' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Add Contact
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyCallLogModal" <?= $houseId === '' ? 'disabled title="Assign a house to this account first"' : '' ?>>
                    <i class="bi bi-journal-plus me-1"></i>Log Call
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyExportModal">
                    <i class="bi bi-download me-1"></i>Export
                </button>
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
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-emergency-log-contact" data-contact-name="<?= e($contact['name'] ?? '') ?>" data-contact-phone="<?= e($contact['phone'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#emergencyCallLogModal" title="Log Emergency Call">
                                        <i class="bi bi-journal-plus me-1"></i>Log Call
                                    </button>
                                    <?php if ($houseId !== '' && (string) ($contact['houseId'] ?? '') === $houseId): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactEditModal-<?= e($contactId) ?>" title="Edit Contact">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
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
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyCallLogModal" <?= $houseId === '' ? 'disabled title="Assign a house to this account first"' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Record New Call
                </button>
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
                                        <td class="fw-medium"><?= e($inc['title'] ?? $inc['contactName'] ?? '—') ?></td>
                                        <td class="small"><?= e(mb_strimwidth((string)($inc['notes'] ?? '—'), 0, 80, '...')) ?></td>
                                        <td><?= e($inc['triggeredByName'] ?? 'Staff') ?></td>
                                        <td class="text-end">
                                            <?php if ($incId !== ''): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#emergencyLogViewModal-<?= e($incId) ?>">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
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
<div class="modal fade" id="emergencyBroadcastModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>">
                <input type="hidden" name="action" value="broadcast">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-broadcast me-2"></i>Broadcast to <?= e($houseName) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">This alert will be visible only to users assigned to <?= e($houseName) ?>. It sends in-app notifications, not SMS.</div>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label" for="emergencyBroadcastTitle">Alert title</label><input class="form-control" id="emergencyBroadcastTitle" name="title" maxlength="120" required></div>
                        <div class="col-md-4">
                            <label class="form-label" for="emergencyType">Emergency category</label>
                            <select class="form-select" id="emergencyType" name="emergencyType"><option value="fire">Fire & evacuation</option><option value="medical">Medical</option><option value="security" selected>Security</option><option value="weather">Severe weather</option><option value="power">Utility / power</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="emergencySeverity">Severity</label>
                            <select class="form-select" id="emergencySeverity" name="severity"><option value="critical">Critical</option><option value="high">High</option></select>
                        </div>
                        <div class="col-12"><label class="form-label" for="emergencyBroadcastMessage">Instructions / details</label><textarea class="form-control" id="emergencyBroadcastMessage" name="message" rows="4" maxlength="2000" required></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger" onclick="return confirm('Send this emergency alert to users assigned to your house now?')"><i class="bi bi-broadcast me-1"></i>Send house alert</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="emergencyContactAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>">
                <input type="hidden" name="action" value="add_contact">
                <div class="modal-header"><h5 class="modal-title">Add Contact for <?= e($houseName) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Contact / organization name</label><input class="form-control" name="name" required></div>
                        <div class="col-md-6"><label class="form-label">Role / title</label><input class="form-control" name="roleTitle"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" required></div>
                        <div class="col-md-6"><label class="form-label">Alternative phone</label><input class="form-control" name="altPhone"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
                        <div class="col-md-6"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="critical">Critical</option><option value="high">High</option><option value="normal" selected>Normal</option></select></div>
                        <div class="col-12"><label class="form-label">Location / station address</label><input class="form-control" name="location"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save contact</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="emergencyCallLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>">
                <input type="hidden" name="action" value="create_log">
                <div class="modal-header"><h5 class="modal-title">Log Emergency Call — <?= e($houseName) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Contacted agency / responder</label><input class="form-control" name="contactName" id="emergencyLogContactName" required></div>
                        <div class="col-md-6"><label class="form-label">Phone number called</label><input class="form-control" name="contactPhone" id="emergencyLogContactPhone"></div>
                        <div class="col-md-6"><label class="form-label">Involved student (optional)</label><select class="form-select" name="studentId"><option value="">None / house-wide incident</option><?php foreach ($students as $student): ?><option value="<?= e($student['id'] ?? '') ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: 'Student') ?><?= !empty($student['admissionNo']) ? ' [' . e($student['admissionNo']) . ']' : '' ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Incident reason / type</label><input class="form-control" name="reason"></div>
                        <div class="col-12"><label class="form-label">Call summary & discussion</label><textarea class="form-control" name="notes" rows="3" required></textarea></div>
                        <div class="col-12"><label class="form-label">Immediate action taken</label><textarea class="form-control" name="actionTaken" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save call log</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="emergencyExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Export <?= e($houseName) ?> Emergency Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p class="mb-2">Download this house's contacts and emergency call / alert logs as a CSV file.</p><div class="alert alert-danger d-none mb-0" data-emergency-export-error role="alert"></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-emergency-export-url="<?= e($exportUrl) ?>"><i class="bi bi-download me-1"></i>Download CSV</button></div>
        </div>
    </div>
</div>
<?php foreach ($contacts as $contact): ?>
    <?php $contactId = (string) ($contact['id'] ?? ''); ?>
    <?php if ($contactId !== '' && $houseId !== '' && (string) ($contact['houseId'] ?? '') === $houseId): ?>
        <div class="modal fade" id="contactEditModal-<?= e($contactId) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
                <form method="POST" action="<?= e($indexUrl) ?>">
                    <input type="hidden" name="action" value="update_contact"><input type="hidden" name="id" value="<?= e($contactId) ?>">
                    <div class="modal-header"><h5 class="modal-title">Edit Emergency Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body"><div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Contact name</label><input name="name" class="form-control" value="<?= e($contact['name'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Role / title</label><input name="roleTitle" class="form-control" value="<?= e($contact['roleTitle'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($contact['phone'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Alternative phone</label><input name="altPhone" class="form-control" value="<?= e($contact['altPhone'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($contact['email'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="critical" <?= ($contact['priority'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option><option value="high" <?= ($contact['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option><option value="normal" <?= ($contact['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option></select></div>
                        <div class="col-12"><label class="form-label">Location / station address</label><input name="location" class="form-control" value="<?= e($contact['location'] ?? '') ?>"></div>
                    </div></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
                </form>
            </div></div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php foreach ($incidents as $inc): ?>
    <?php $incidentId = (string) ($inc['id'] ?? ''); ?>
    <?php if ($incidentId !== ''): ?>
        <div class="modal fade" id="emergencyLogViewModal-<?= e($incidentId) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><?= e(!empty($inc['isBroadcast']) ? 'Emergency Alert Details' : 'Emergency Call Details') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><dl class="row mb-0">
                    <dt class="col-sm-4">Timestamp</dt><dd class="col-sm-8"><?= !empty($inc['triggeredAt']) ? e(date('M d, Y H:i', strtotime((string) $inc['triggeredAt']))) : '—' ?></dd>
                    <dt class="col-sm-4">Contact / alert</dt><dd class="col-sm-8"><?= e($inc['title'] ?? $inc['contactName'] ?? '—') ?></dd>
                    <?php if (!empty($inc['contactPhone'])): ?><dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= e($inc['contactPhone']) ?></dd><?php endif; ?>
                    <?php if (!empty($inc['studentName'])): ?><dt class="col-sm-4">Student</dt><dd class="col-sm-8"><?= e($inc['studentName']) ?></dd><?php endif; ?>
                    <?php if (!empty($inc['reason'])): ?><dt class="col-sm-4">Reason</dt><dd class="col-sm-8"><?= e($inc['reason']) ?></dd><?php endif; ?>
                    <dt class="col-sm-4">Logged by</dt><dd class="col-sm-8"><?= e($inc['triggeredByName'] ?? 'Staff') ?></dd>
                    <dt class="col-sm-4">Summary / instructions</dt><dd class="col-sm-8"><?= nl2br(e($inc['notes'] ?? '—')) ?></dd>
                    <?php if (!empty($inc['actionTaken'])): ?><dt class="col-sm-4">Action taken</dt><dd class="col-sm-8"><?= nl2br(e($inc['actionTaken'])) ?></dd><?php endif; ?>
                </dl></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div></div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<script>
document.querySelectorAll('.js-emergency-log-contact').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('emergencyLogContactName').value = button.dataset.contactName || '';
        document.getElementById('emergencyLogContactPhone').value = button.dataset.contactPhone || '';
    });
});

document.querySelectorAll('[data-emergency-export-url]').forEach(function (button) {
    button.addEventListener('click', async function () {
        const errorBox = document.querySelector('[data-emergency-export-error]');
        errorBox.classList.add('d-none');
        SDS.showButtonLoading(button);
        try {
            const response = await fetch(button.dataset.emergencyExportUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('text/csv')) {
                throw new Error('The CSV export could not be generated. Please try again.');
            }
            const blob = await response.blob();
            const disposition = response.headers.get('content-disposition') || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = match ? match[1] : 'house-emergency-logs.csv';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
        } catch (error) {
            errorBox.textContent = error.message || 'The CSV export could not be generated.';
            errorBox.classList.remove('d-none');
        } finally {
            SDS.hideButtonLoading(button);
        }
    });
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>