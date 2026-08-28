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
} $allowedRoles=[ROLE_HOUSE_MASTER,ROLE_HOUSE_MISTRESS]; require APP_ROOT.'/app/middleware/RoleMiddleware/RoleMiddleware.php'; use App\Services\AttendanceService;
$records=AttendanceService::byHouse(current_user()['houseId']??null);header('Content-Type: text/csv');header('Content-Disposition: attachment; filename="house-attendance.csv"');$out=fopen('php://output','w');fputcsv($out,['Date','Student ID','Status','Marked By']);foreach($records as $record){fputcsv($out,[$record['date']??'',$record['studentId']??'',$record['status']??'',$record['markedBy']??'']);}fclose($out);exit;