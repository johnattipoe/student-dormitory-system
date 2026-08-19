<?php $appConfig = app_config(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>

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

    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <?php
    $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    $autoStyles = [];

    if (str_contains($currentScript, '/views/admin/')) $autoStyles[] = 'admin.css';
    if (str_contains($currentScript, '/views/house-master/')) $autoStyles[] = 'house-master.css';
    if (str_contains($currentScript, '/views/houseparent/')) $autoStyles[] = 'houseparent.css';
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
<div class="app-shell d-flex">
