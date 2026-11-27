<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}

$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\NotificationService;
use App\Services\StudentService;

$firebase = FirebaseService::getInstance();
$user = current_user() ?? [];
$userId = (string) current_user_id();
$userName = trim((string) ($user['name'] ?? '')) ?: 'Administrator';
$indexUrl = url('views/admin/emergency-contacts/index.php');
$exportUrl = url('views/admin/emergency-contacts/export/export.php');
$allContacts = $firebase->getCollection('emergency_contacts', [], 500);

if (empty($allContacts)) {
    $seed = [
        ['name' => 'Campus Clinic / Infirmary', 'roleTitle' => 'School Nurse Desk', 'phone' => '+233 24 000 0001', 'email' => 'clinic@dormitory.edu', 'priority' => 'critical', 'status' => 'active'],
        ['name' => 'Campus Security Main Gate', 'roleTitle' => 'Security Office', 'phone' => '+233 24 000 0002', 'email' => 'security@dormitory.edu', 'priority' => 'critical', 'status' => 'active'],
        ['name' => 'Local Police Dispatch', 'roleTitle' => 'Emergency Police Command', 'phone' => '191 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active'],
        ['name' => 'National Fire & Rescue Service', 'roleTitle' => 'Fire Operations', 'phone' => '192 / 112', 'email' => '', 'priority' => 'critical', 'status' => 'active'],
        ['name' => 'District Hospital Emergency Unit', 'roleTitle' => 'Hospital Triage Desk', 'phone' => '+233 20 000 0004', 'email' => 'hospital@dormitory.edu', 'priority' => 'critical', 'status' => 'active'],
        ['name' => 'Facilities & Electrical Maintenance', 'roleTitle' => 'Emergency Repairs & Power', 'phone' => '+233 24 000 0003', 'email' => 'facilities@dormitory.edu', 'priority' => 'high', 'status' => 'active'],
    ];
    foreach ($seed as $contact) {
        $firebase->addDocument('emergency_contacts', $contact);
    }
    $allContacts = $firebase->getCollection('emergency_contacts', [], 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $broadcastRecorded = false;
    try {
        if ($action === 'add_contact') {
            $name = trim((string) sanitize($_POST['name'] ?? ''));
            $phone = trim((string) sanitize($_POST['phone'] ?? ''));
            if ($name === '' || $phone === '') {
                throw new \InvalidArgumentException('Contact name and phone number are required.');
            }
            $priority = (string) ($_POST['priority'] ?? 'normal');
            if (!in_array($priority, ['critical', 'high', 'normal'], true)) {
                throw new \InvalidArgumentException('Select a valid contact priority.');
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
                'addedBy' => $userId,
            ]);
            flash('success', 'Emergency contact added to the directory.');
        } elseif ($action === 'update_contact') {
            $contactId = trim((string) sanitize($_POST['id'] ?? ''));
            $contact = $contactId !== '' ? $firebase->getDocument('emergency_contacts', $contactId) : null;
            if (!$contact) {
                throw new \InvalidArgumentException('Emergency contact not found.');
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
            $contactName = trim((string) sanitize($_POST['contactName'] ?? ''));
            $notes = trim((string) sanitize($_POST['notes'] ?? ''));
            if ($contactName === '' || $notes === '') {
                throw new \InvalidArgumentException('Contact name and call summary are required.');
            }
            $studentId = trim((string) sanitize($_POST['studentId'] ?? ''));
            $studentName = '';
            $student = null;
            if ($studentId !== '') {
                $student = StudentService::find($studentId);
                $studentName = trim((string) ($student['firstName'] ?? '') . ' ' . (string) ($student['lastName'] ?? ''));
                if (!$student || $studentName === '') {
                    throw new \InvalidArgumentException('Select a valid student.');
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
                'houseId' => $studentId !== '' ? (string) ($student['houseId'] ?? '') : '',
                'triggeredBy' => $userId,
                'triggeredByName' => $userName . ' (Administrator)',
                'triggeredAt' => date(DATE_ATOM),
            ], static fn($value) => $value !== ''));
            flash('success', 'Emergency call record logged successfully.');
        } elseif ($action === 'broadcast') {
            $title = trim((string) sanitize($_POST['title'] ?? ''));
            $message = trim((string) sanitize($_POST['message'] ?? ''));
            $audience = (string) ($_POST['audience'] ?? 'all');
            $emergencyType = (string) ($_POST['emergencyType'] ?? 'security');
            $severity = (string) ($_POST['severity'] ?? 'critical');
            if ($title === '' || $message === '') {
                throw new \InvalidArgumentException('Alert title and message are required.');
            }
            if (!in_array($audience, ['all', 'house_master'], true)) {
                throw new \InvalidArgumentException('Select a valid broadcast audience.');
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
                'audience' => $audience,
                'isBroadcast' => true,
                'contactName' => 'CAMPUS EMERGENCY BROADCAST',
                'triggeredBy' => $userId,
                'triggeredByName' => $userName . ' (Administrator)',
                'triggeredAt' => $now,
            ]);
            $broadcastRecorded = true;
            $firebase->addDocument('announcements', [
                'title' => 'EMERGENCY ALERT: ' . $title,
                'message' => $message,
                'audience' => $audience === 'house_master' ? 'role' : 'all',
                'targetRole' => $audience === 'house_master' ? ROLE_HOUSE_MASTER : '',
                'type' => 'danger',
                'status' => 'published',
                'isUrgent' => true,
                'createdBy' => $userId,
                'createdByName' => $userName . ' (Administrator)',
                'publishedAt' => $now,
            ]);
            $notification = new NotificationService();
            $payload = ['title' => 'EMERGENCY: ' . $title, 'message' => $message, 'type' => 'danger', 'isUrgent' => true];
            $recipientRoles = $audience === 'house_master' ? [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS] : [null];
            $notificationCount = 0;
            foreach ($recipientRoles as $recipientRole) {
                $wheres = $recipientRole === null ? [] : [['role', '=', $recipientRole]];
                foreach ($firebase->getAllCollection('users', $wheres) as $recipient) {
                    if (($recipient['status'] ?? 'active') !== 'active') {
                        continue;
                    }
                    $recipientId = (string) ($recipient['uid'] ?? $recipient['id'] ?? '');
                    if ($recipientId === '') {
                        continue;
                    }
                    $result = $notification->create(array_merge($payload, [
                        'recipientType' => 'user',
                        'userId' => $recipientId,
                    ]));
                    $notificationCount += !empty($result['success']) ? 1 : 0;
                }
            }
            if ($notificationCount < 1) {
                flash('warning', 'The emergency alert was recorded, but no recipients were confirmed for notification.');
            } else {
                flash('success', 'Emergency alert broadcast to ' . $notificationCount . ' recipient(s).');
            }
        } else {
            throw new \InvalidArgumentException('Unknown emergency action.');
        }
    } catch (\Throwable $e) {
        if ($action === 'broadcast' && $broadcastRecorded) {
            flash('warning', 'The emergency alert record was saved, but a later publishing or notification step failed: ' . $e->getMessage());
        } else {
            flash('error', 'Emergency action failed: ' . $e->getMessage());
        }
    }
    redirect($indexUrl);
}

$contacts = array_values(array_filter($allContacts, static fn($c) => ($c['status'] ?? 'active') === 'active'));

usort($contacts, static function ($a, $b) {
    $weights = ['critical' => 0, 'high' => 1, 'normal' => 2];
    $wA = $weights[$a['priority'] ?? 'normal'] ?? 3;
    $wB = $weights[$b['priority'] ?? 'normal'] ?? 3;
    return $wA <=> $wB;
});

$totalContacts = count($contacts);
$criticalContacts = count(array_filter($contacts, fn($c) => ($c['priority'] ?? '') === 'critical'));
$todayDate = date('Y-m-d');
$tomorrowDate = date('Y-m-d', strtotime('+1 day'));
$todayCalls = $firebase->count('emergency_incidents', [
    ['triggeredAt', '>=', $todayDate],
    ['triggeredAt', '<', $tomorrowDate],
]);

$pageTitle = 'Emergency Contacts & Dispatch';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Contacts', 'href' => url('views/admin/emergency-contacts/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1 text-danger"><i class="bi bi-shield-exclamation me-2"></i> Campus Emergency Directory & Dispatch Command</h5>
                <p class="text-muted mb-0">System-wide emergency services, institutional first responders, and emergency alerts.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyBroadcastModal">
                    <i class="bi bi-broadcast me-1"></i> Broadcast Alert
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyContactAddModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Contact
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyCallLogModal">
                    <i class="bi bi-journal-plus me-1"></i> Log Call
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyExportModal">
                    <i class="bi bi-download me-1"></i> Export Directory & Logs
                </button>
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
                    <span class="small text-muted">Active responder services</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-warning">
                    <small class="text-muted">Calls Logged Today</small>
                    <strong class="fs-2 text-warning my-1"><?= e((string) $todayCalls) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
        </div>

        <!-- Emergency Directory Cards -->
        <h6 class="fw-bold mb-3"><i class="bi bi-telephone-fill me-2 text-danger"></i> First Responder Directory</h6>
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
                            <button type="button" class="btn btn-sm btn-outline-secondary js-emergency-log-contact" data-contact-name="<?= e($contact['name'] ?? '') ?>" data-contact-phone="<?= e($contact['phone'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#emergencyCallLogModal">
                                <i class="bi bi-journal-plus me-1"></i> Log Call
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactEditModal-<?= e($contactId) ?>" title="Edit contact">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Logs Table -->
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Emergency Dispatch & Call Logs</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emergencyCallLogModal">
                    <i class="bi bi-plus-circle me-1"></i> Record New Call
                </button>
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
                                <th>Call Summary & Discussion</th>
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
                                    <td><strong><?= e($inc['title'] ?? $inc['contactName'] ?? '—') ?></strong></td>
                                    <td><?= e(mb_strimwidth((string)($inc['notes'] ?? '—'), 0, 80, '...')) ?></td>
                                    <td><?= e($inc['triggeredByName'] ?? 'Staff') ?></td>
                                    <td class="text-end">
                                        <?php if ($incId !== ''): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#emergencyLogViewModal-<?= e($incId) ?>">
                                                <i class="bi bi-eye"></i> View
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
<div class="modal fade" id="emergencyBroadcastModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>">
                <input type="hidden" name="action" value="broadcast">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-broadcast me-2"></i>Broadcast Emergency Alert</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning"><strong>Urgent action:</strong> this immediately records an alert and sends in-app notifications to the selected audience. It does not send SMS.</div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="emergencyBroadcastTitle">Alert title</label>
                            <input class="form-control" id="emergencyBroadcastTitle" name="title" maxlength="120" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="emergencyType">Emergency category</label>
                            <select class="form-select" id="emergencyType" name="emergencyType">
                                <option value="fire">Fire & evacuation</option>
                                <option value="medical">Medical</option>
                                <option value="security" selected>Security</option>
                                <option value="weather">Severe weather</option>
                                <option value="power">Utility / power</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="emergencyAudience">Audience</label>
                            <select class="form-select" id="emergencyAudience" name="audience">
                                <option value="all">Entire school</option>
                                <option value="house_master">House Masters & Mistresses</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="emergencySeverity">Severity</label>
                            <select class="form-select" id="emergencySeverity" name="severity">
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="emergencyBroadcastMessage">Instructions / details</label>
                            <textarea class="form-control" id="emergencyBroadcastMessage" name="message" rows="4" maxlength="2000" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Send this emergency alert to the selected audience now?')"><i class="bi bi-broadcast me-1"></i>Send alert</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="emergencyContactAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= e($indexUrl) ?>">
                <input type="hidden" name="action" value="add_contact">
                <div class="modal-header"><h5 class="modal-title">Add Emergency Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
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
                <div class="modal-header"><h5 class="modal-title">Log Emergency Call</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Contacted agency / responder</label><input class="form-control" name="contactName" id="emergencyLogContactName" required></div>
                        <div class="col-md-6"><label class="form-label">Phone number called</label><input class="form-control" name="contactPhone" id="emergencyLogContactPhone"></div>
                        <div class="col-md-6"><label class="form-label">Involved student (optional)</label><select class="form-select" name="studentId" data-admin-student-options="<?= e(url('views/admin/emergency-contacts/student-options.php')) ?>" data-placeholder="None / dormitory-wide incident"><option value="">None / dormitory-wide incident</option></select></div>
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
            <div class="modal-header"><h5 class="modal-title">Export Emergency Directory & Logs</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p class="mb-2">Download the emergency contacts and recent call / broadcast logs as a CSV file.</p><div class="alert alert-danger d-none mb-0" data-emergency-export-error role="alert"></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" data-emergency-export-url="<?= e($exportUrl) ?>"><i class="bi bi-download me-1"></i>Download CSV</button></div>
        </div>
    </div>
</div>
<?php foreach ($contacts as $contact): ?>
    <?php $contactId = (string) ($contact['id'] ?? ''); ?>
    <div class="modal fade" id="contactEditModal-<?= e($contactId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= e($indexUrl) ?>"><input type="hidden" name="action" value="update_contact"><input type="hidden" name="id" value="<?= e($contactId) ?>"><div class="modal-header"><h5 class="modal-title">Edit Emergency Contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Contact name</label><input name="name" class="form-control" value="<?= e($contact['name'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Role / title</label><input name="roleTitle" class="form-control" value="<?= e($contact['roleTitle'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($contact['phone'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label">Alternative phone</label><input name="altPhone" class="form-control" value="<?= e($contact['altPhone'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($contact['email'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="critical" <?= ($contact['priority'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option><option value="high" <?= ($contact['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option><option value="normal" <?= ($contact['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option></select></div><div class="col-12"><label class="form-label">Location / station address</label><input name="location" class="form-control" value="<?= e($contact['location'] ?? '') ?>"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div>
    </div>
<?php endforeach; ?>
<?php foreach ($incidents as $inc): ?>
    <?php $incId = (string) ($inc['id'] ?? ''); ?>
    <div class="modal fade" id="emergencyLogViewModal-<?= e($incId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?= e(!empty($inc['isBroadcast']) ? 'Emergency Alert Details' : 'Emergency Call Details') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5">Timestamp</dt><dd class="col-sm-7"><?= !empty($inc['triggeredAt']) ? e(date('M d, Y H:i', strtotime((string) $inc['triggeredAt']))) : '—' ?></dd><dt class="col-sm-5">Contact / alert</dt><dd class="col-sm-7"><?= e($inc['title'] ?? $inc['contactName'] ?? '—') ?></dd><?php if (!empty($inc['contactPhone'])): ?><dt class="col-sm-5">Phone</dt><dd class="col-sm-7"><?= e($inc['contactPhone']) ?></dd><?php endif; ?><?php if (!empty($inc['reason'])): ?><dt class="col-sm-5">Reason</dt><dd class="col-sm-7"><?= e($inc['reason']) ?></dd><?php endif; ?><dt class="col-sm-5">Logged by</dt><dd class="col-sm-7"><?= e($inc['triggeredByName'] ?? 'Staff') ?></dd><dt class="col-sm-5">Summary / instructions</dt><dd class="col-sm-7"><?= nl2br(e($inc['notes'] ?? '—')) ?></dd><?php if (!empty($inc['actionTaken'])): ?><dt class="col-sm-5">Action taken</dt><dd class="col-sm-7"><?= nl2br(e($inc['actionTaken'])) ?></dd><?php endif; ?></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>
    </div>
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
            link.download = match ? match[1] : 'emergency-directory-and-logs.csv';
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
