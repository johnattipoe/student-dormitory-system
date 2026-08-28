<?php $appConfig = app_config(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>
    <?php $advancedConfig = $appConfig['advanced'] ?? []; ?>
    <style>
        :root { --app-primary: <?= e($advancedConfig['primary_color'] ?? '#1f6feb') ?>; }
        .portal-startup-loader[hidden] { display: none !important; }
    </style>

    <!-- CSS libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <!-- Core JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <?php
    $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    $autoStyles = [];

    if (str_contains($currentScript, '/views/admin/')) $autoStyles[] = 'admin.css';
    if (str_contains($currentScript, '/views/house-master/')) $autoStyles[] = 'house-master.css';
    if (str_contains($currentScript, '/views/senior-houseparent/')) $autoStyles[] = 'senior-houseparent.css';
    if (str_contains($currentScript, '/views/nurse/')) $autoStyles[] = 'nurse.css';
    if (str_contains($currentScript, '/views/security/')) $autoStyles[] = 'security.css';
    if (str_contains($currentScript, '/views/student/')) $autoStyles[] = 'student.css';
    if (str_contains($currentScript, '/reports/') || str_ends_with($currentScript, '/reports.php')) $autoStyles[] = 'reports.css';

    $pageStyles = array_values(array_unique(array_merge($pageStyles ?? [], $autoStyles)));
    ?>
    <?php foreach ($pageStyles as $style): ?>
        <link rel="stylesheet" href="<?= asset('css/' . $style) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>
<?php
$portalLoaderName = 'Student Dormitory System';
if (str_contains($currentScript, '/views/admin/')) {
    $portalLoaderName = 'Admin Portal';
} elseif (str_contains($currentScript, '/views/student/')) {
    $portalLoaderName = 'Student Portal';
} elseif (str_contains($currentScript, '/views/house-master/')) {
    $portalLoaderName = (function_exists('current_role') && current_role() === ROLE_HOUSE_MISTRESS) ? 'House Mistress Portal' : 'House Master Portal';
} elseif (str_contains($currentScript, '/views/senior-houseparent/')) {
    $portalLoaderName = 'Senior Houseparent Portal';
} elseif (str_contains($currentScript, '/views/security/')) {
    $portalLoaderName = 'Security Portal';
} elseif (str_contains($currentScript, '/views/nurse/')) {
    $portalLoaderName = 'Nurse Portal';
}
?>
<div id="portalStartupLoader" class="portal-startup-loader is-hidden" hidden role="status" aria-live="polite" aria-label="Loading portal">
    <div class="portal-startup-card">
        <div class="portal-startup-title"><?= e($portalLoaderName) ?></div>
        <div class="portal-startup-text">Loading page resources...</div>
        <div class="portal-startup-progress" aria-hidden="true"><span></span></div>
    </div>
</div>
<div class="app-shell d-flex">
