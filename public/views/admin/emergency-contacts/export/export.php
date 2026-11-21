<?php
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

try {
    $firebase = FirebaseService::getInstance();
    $contacts = $firebase->getCollection('emergency_contacts', [], 500);
    $incidents = $firebase->getCollection('emergency_incidents', [], 500);
    $contacts = array_values(array_filter($contacts, static fn(array $contact): bool => ($contact['status'] ?? 'active') === 'active'));
    usort($incidents, static fn(array $first, array $second): int => strcmp(
        (string) ($second['triggeredAt'] ?? ''),
        (string) ($first['triggeredAt'] ?? '')
    ));

    $csvValue = static function ($value): string {
        $value = (string) ($value ?? '');
        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value) ? "'" . $value : $value;
    };

    $output = fopen('php://temp', 'w+');
    if ($output === false) {
        throw new RuntimeException('Unable to open the CSV output stream.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Record type', 'Timestamp', 'Contact / alert', 'Role / category', 'Phone', 'Email', 'Priority / severity', 'Reason', 'Summary / instructions', 'Action taken', 'Student', 'Logged by', 'House', 'Audience']);
    foreach ($contacts as $contact) {
        fputcsv($output, [
            'Contact', $csvValue($contact['createdAt'] ?? ''), $csvValue($contact['name'] ?? ''),
            $csvValue($contact['roleTitle'] ?? ''), $csvValue($contact['phone'] ?? ''),
            $csvValue($contact['email'] ?? ''), $csvValue($contact['priority'] ?? ''),
            '', '', '', '', $csvValue($contact['addedBy'] ?? ''), $csvValue($contact['houseId'] ?? ''), '',
        ]);
    }
    foreach ($incidents as $incident) {
        fputcsv($output, [
            !empty($incident['isBroadcast']) ? 'Broadcast' : 'Call log',
            $csvValue($incident['triggeredAt'] ?? $incident['createdAt'] ?? ''),
            $csvValue($incident['title'] ?? $incident['contactName'] ?? ''),
            $csvValue($incident['emergencyType'] ?? $incident['reason'] ?? ''),
            $csvValue($incident['contactPhone'] ?? ''), '',
            $csvValue($incident['severity'] ?? ''), $csvValue($incident['reason'] ?? ''),
            $csvValue($incident['notes'] ?? ''), $csvValue($incident['actionTaken'] ?? ''),
            $csvValue($incident['studentName'] ?? ''), $csvValue($incident['triggeredByName'] ?? ''),
            $csvValue($incident['houseId'] ?? ''), $csvValue($incident['audience'] ?? ''),
        ]);
    }
    rewind($output);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="emergency-directory-and-logs.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    fpassthru($output);
    fclose($output);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to export emergency records: ' . $e->getMessage();
}
exit;