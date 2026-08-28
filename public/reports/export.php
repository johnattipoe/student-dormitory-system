<?php
require __DIR__ . '/../bootstrap.php';

use App\Services\ReportService;

$type = strtolower(trim((string) ($_GET['type'] ?? 'dashboard')));
$format = strtolower(trim((string) ($_GET['format'] ?? 'pdf')));

$allowedReports = [
    'dashboard' => [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT],
    'attendance' => [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT],
    'occupancy' => [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT],
    'students' => [ROLE_ADMIN, ROLE_HOUSE_MASTER],
    'visitors' => [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY],
    'incidents' => [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE],
    'medical' => [ROLE_ADMIN, ROLE_NURSE],
    'house_master' => [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS],
    'senior-houseparent' => [ROLE_SENIOR_HOUSEPARENT],
    'student_attendance' => [ROLE_STUDENT],
];

if (!isset($allowedReports[$type])) {
    http_response_code(404);
    exit('Report not found.');
}

$allowedRoles = $allowedReports[$type];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

if (!in_array($format, ['pdf', 'csv', 'xls'], true)) {
    http_response_code(400);
    exit('Unsupported export format.');
}

$service = new ReportService();
$data = match ($type) {
    'house_master' => house_master_report_data(),
    'senior-houseparent' => senior_houseparent_report_data(),
    'student_attendance' => student_attendance_report_data(),
    default => $service->{$type === 'dashboard' ? 'dashboard' : $type}(),
};
$title = ucwords(str_replace('_', ' ', $type)) . ' Report';
$rows = report_export_rows($data);
$filename = strtolower(str_replace(' ', '-', $title)) . '-' . date('Y-m-d-His');

if ($format === 'csv') {
    export_report_csv($filename . '.csv', $title, $rows);
}

if ($format === 'xls') {
    export_report_xls($filename . '.xls', $title, $rows);
}

export_report_pdf($filename . '.pdf', $title, $rows);

function report_export_rows(array $data, string $prefix = ''): array
{
    $rows = [];

    foreach ($data as $key => $value) {
        $label = trim($prefix . ' ' . ucwords(str_replace(['_', '-'], ' ', (string) $key)));

        if (is_array($value)) {
            $rows = array_merge($rows, report_export_rows($value, $label));
            continue;
        }

        $rows[] = [$label, (string) $value];
    }

    return $rows;
}

function export_report_csv(string $filename, string $title, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, [$title]);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['Metric', 'Value']);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

function export_report_xls(string $filename, string $title, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<table border="1">';
    echo '<tr><th colspan="2">' . e($title) . '</th></tr>';
    echo '<tr><td>Generated At</td><td>' . e(date('Y-m-d H:i:s')) . '</td></tr>';
    echo '<tr><th>Metric</th><th>Value</th></tr>';

    foreach ($rows as [$label, $value]) {
        echo '<tr><td>' . e($label) . '</td><td>' . e($value) . '</td></tr>';
    }

    echo '</table>';
    exit;
}

function export_report_pdf(string $filename, string $title, array $rows): void
{
    require_once APP_ROOT . '/fpdf19/fpdf.php';

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, 'Generated At: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(120, 9, 'Metric', 1);
    $pdf->Cell(60, 9, 'Value', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 10);
    foreach ($rows as [$label, $value]) {
        $pdf->Cell(120, 8, substr($label, 0, 65), 1);
        $pdf->Cell(60, 8, substr($value, 0, 35), 1);
        $pdf->Ln();
    }

    $pdf->Output('D', $filename);
    exit;
}

function house_master_report_data(): array
{
    $date = sanitize($_GET['date'] ?? date('Y-m-d'));
    $houseId = current_user()['houseId'] ?? null;
    $summary = App\Services\AttendanceService::summary($date, $houseId);
    $students = App\Services\StudentService::all($houseId);
    $incidents = (new App\Services\IncidentService())->byHouse($houseId, true);

    return [
        'date' => $date,
        'students' => count($students),
        'attendance' => $summary,
        'openIncidents' => count($incidents),
    ];
}

function senior_houseparent_report_data(): array
{
    $date = sanitize($_GET['date'] ?? date('Y-m-d'));
    $summary = App\Services\AttendanceService::summary($date);
    $students = App\Services\StudentService::all();
    $visitors = (new App\Services\VisitorService())->all();
    $incidents = array_values(array_filter(
        (new App\Services\IncidentService())->all(),
        fn($incident) => ($incident['status'] ?? 'open') === 'open'
    ));

    return [
        'date' => $date,
        'students' => count($students),
        'attendance' => $summary,
        'visitors' => count($visitors),
        'openIncidents' => count($incidents),
    ];
}

function student_attendance_report_data(): array
{
    $studentId = (string) (current_user()['uid'] ?? '');
    $records = App\Services\AttendanceService::history($studentId, 200);
    $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

    foreach ($records as $record) {
        $status = (string) ($record['status'] ?? 'absent');
        if (isset($summary[$status])) {
            $summary[$status]++;
        }
    }

    return [
        'student' => current_user()['name'] ?? current_user()['email'] ?? 'Student',
        'attendance' => $summary,
        'totalRecords' => count($records),
    ];
}
