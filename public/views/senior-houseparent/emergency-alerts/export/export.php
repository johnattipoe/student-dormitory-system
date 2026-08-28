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
$type = sanitize($_GET['type'] ?? 'all');

$filename = 'emergency-directory-and-logs-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

// Section 1: Emergency Directory
fputcsv($output, ['--- EMERGENCY CONTACT DIRECTORY ---']);
fputcsv($output, ['Name / Organization', 'Role / Department', 'Phone', 'Alternative Phone', 'Email', 'Location', 'Priority', 'Status']);

$contacts = $firebase->getCollection('emergency_contacts', [], 500);
foreach ($contacts as $c) {
    fputcsv($output, [
        $c['name'] ?? 'Contact',
        $c['roleTitle'] ?? 'Emergency Responder',
        $c['phone'] ?? '—',
        $c['altPhone'] ?? '—',
        $c['email'] ?? '—',
        $c['location'] ?? '—',
        ucfirst((string)($c['priority'] ?? 'normal')),
        ucfirst((string)($c['status'] ?? 'active')),
    ]);
}

fputcsv($output, []);
fputcsv($output, ['--- EMERGENCY DISPATCH & CALL LOGS ---']);
fputcsv($output, ['Timestamp', 'Contacted Responder', 'Phone', 'Reason', 'Incident & Discussion Notes', 'Action Taken', 'Logged By']);

$incidents = $firebase->getCollection('emergency_incidents', [], 500);
usort($incidents, static fn($a, $b) => strcmp((string) ($b['triggeredAt'] ?? ''), (string) ($a['triggeredAt'] ?? '')));

foreach ($incidents as $inc) {
    $rawTime = (string) ($inc['triggeredAt'] ?? $inc['createdAt'] ?? '');
    $formattedTime = $rawTime !== '' ? (date('Y-m-d H:i:s', strtotime($rawTime)) ?: $rawTime) : '—';
    fputcsv($output, [
        $formattedTime,
        $inc['contactName'] ?? '—',
        $inc['contactPhone'] ?? '—',
        $inc['reason'] ?? 'Emergency',
        $inc['notes'] ?? '—',
        $inc['actionTaken'] ?? '—',
        $inc['triggeredByName'] ?? 'Staff',
    ]);
}

fclose($output);
exit;

