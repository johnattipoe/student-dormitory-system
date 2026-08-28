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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$firebase = FirebaseService::getInstance();
$medicalRecords = $firebase->getCollection('medical_records', [], 1000);

usort($medicalRecords, function ($a, $b) {
    $tA = strtotime((string) ($a['createdAt'] ?? $a['date'] ?? ''));
    $tB = strtotime((string) ($b['createdAt'] ?? $b['date'] ?? ''));
    return $tB <=> $tA;
});

$filename = 'clinical-audit-trail-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

fputcsv($output, ['Timestamp', 'Student Patient', 'Diagnosis / Condition', 'Treatment Administered', 'Severity', 'Attending Nurse', 'Notes']);

foreach ($medicalRecords as $mr) {
    $rawTime = (string) ($mr['createdAt'] ?? $mr['date'] ?? '');
    $formattedTime = $rawTime !== '' ? (date('Y-m-d H:i:s', strtotime($rawTime)) ?: $rawTime) : '—';
    fputcsv($output, [
        $formattedTime,
        $mr['studentName'] ?? $mr['studentId'] ?? 'Student',
        $mr['diagnosis'] ?? $mr['condition'] ?? '—',
        $mr['treatment'] ?? '—',
        ucfirst((string)($mr['severity'] ?? 'normal')),
        $mr['recordedByName'] ?? $mr['nurseName'] ?? 'Nurse',
        $mr['notes'] ?? $mr['description'] ?? '—',
    ]);
}

fclose($output);
exit;

