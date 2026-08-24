<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?= e($appConfig['name'] ?? 'Student Dormitory System') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center">
  <div id="startupLoader" class="startup-loader is-hidden" hidden role="status" aria-live="polite" aria-label="Starting application">
    <div class="startup-loader-card">
      <div class="startup-logo">
        <i class="bi bi-building"></i>
      </div>
      <div class="startup-loader-title"><?= e($appConfig['name'] ?? 'Student Dormitory System') ?></div>
      <div class="startup-loader-text">Preparing your secure portal...</div>
      <div class="startup-progress" aria-hidden="true">
        <span></span>
      </div>
    </div>
  </div>

  <div class="login-card card shadow-lg border-0">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <i class="bi bi-building fs-1 text-primary"></i>
        <h4 class="mt-2 mb-0"><?= e($appConfig['name'] ?? 'Student Dormitory System') ?></h4>
        <p class="text-muted small">Sign in to your dashboard</p>
        <?php if (!empty($appConfig['support_email'])): ?>
          <p class="text-muted small mb-0">Need help? <a href="mailto:<?= e($appConfig['support_email']) ?>"><?= e($appConfig['support_email']) ?></a></p>
        <?php endif; ?>
      </div>

      <?php $err = flash('error'); ?>
      <?php if ($err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['expired'])): ?>
        <div class="alert alert-warning py-2">Your session expired. Please log in again.</div>
      <?php endif; ?>

      <form method="POST" action="/login.php">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control" autocomplete="username" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="form-control" autocomplete="current-password" required>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <a href="/forgot-password.php" class="small">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/auth.js"></script>
</body>
</html>
