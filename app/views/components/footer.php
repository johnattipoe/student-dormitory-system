    <?php require APP_ROOT . '/app/views/components/loading.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/pdfmake.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/vfs_fonts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php
    $currentScript = str_replace('\\', '/', $_GET['route'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    $autoScripts = [];

    if (str_contains($currentScript, '/views/admin/')) {
        $autoScripts[] = 'admin.js';
        if (str_ends_with($currentScript, '/views/admin/dashboard.php')) {
            $autoScripts[] = 'dashboard.js';
        }
    }
    if (str_contains($currentScript, '/students/')) $autoScripts[] = 'students.js';
    if (str_contains($currentScript, '/rooms/') || str_contains($currentScript, '/room/')) $autoScripts[] = 'rooms.js';
    if (str_contains($currentScript, '/attendance/')) $autoScripts[] = 'attendance.js';
    if (str_contains($currentScript, '/visitors/') || str_contains($currentScript, '/visitor-') || str_contains($currentScript, '/register-visitor/')) $autoScripts[] = 'visitors.js';
    if (str_contains($currentScript, '/incidents/') || str_contains($currentScript, '/report-incident/')) $autoScripts[] = 'incidents.js';
    if (str_contains($currentScript, '/medical/') || str_contains($currentScript, '/medical-records/') || str_contains($currentScript, '/medical-incidents/') || str_contains($currentScript, '/emergency-cases/') || str_contains($currentScript, '/create-record/') || str_contains($currentScript, '/edit-record/')) $autoScripts[] = 'medical.js';
    if (str_contains($currentScript, '/reports/') || str_ends_with($currentScript, '/reports.php')) $autoScripts[] = 'reports.js';
    if (str_contains($currentScript, '/notifications/')) $autoScripts[] = 'notifications.js';
    if (str_contains($currentScript, '/views/house-master/')) $autoScripts[] = 'house-master.js';
    if (str_contains($currentScript, '/views/houseparent/')) $autoScripts[] = 'houseparent.js';
    if (str_contains($currentScript, '/views/nurse/')) $autoScripts[] = 'nurse.js';
    if (str_contains($currentScript, '/views/security/')) $autoScripts[] = 'security.js';
    if (str_contains($currentScript, '/views/student/')) $autoScripts[] = 'student.js';

    $pageScripts = array_values(array_unique(array_merge($pageScripts ?? [], $autoScripts)));
    ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= asset('js/' . $script) ?>"></script>
    <?php endforeach; ?>
</div><!-- /.app-shell -->
</body>
</html>
