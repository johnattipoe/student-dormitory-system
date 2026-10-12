<?php
return static function (array $accessibleModules): void {
?>
<div class="col-xl-8">
    <section class="card border-0 shadow-sm h-100 permission-dashboard-workspace">
        <div class="card-header permission-section-header">
            <div class="permission-section-title">
                <span class="permission-section-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
                <div>
                    <h2>Your workspace</h2>
                    <p>Open an area available to your role.</p>
                </div>
            </div>
            <span class="permission-section-count"><?= count($accessibleModules) ?> <span>areas</span></span>
        </div>
        <div class="card-body pt-0">
            <?php if ($accessibleModules): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <label class="visually-hidden" for="roleWorkspaceSearch">Search workspace modules</label>
                    <input id="roleWorkspaceSearch" type="search" class="form-control form-control-sm" style="max-width: 280px" placeholder="Search your modules..." data-ts-workspace-filter="#roleWorkspaceGrid" aria-controls="roleWorkspaceGrid">
                    <span class="small text-muted" data-ts-workspace-count aria-live="polite"></span>
                </div>
            <?php endif; ?>
            <?php if (!$accessibleModules): ?>
                <div class="permission-empty-state">
                    <span class="permission-empty-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                    <h3>No workspace areas assigned</h3>
                    <p>Ask an administrator to grant access to the modules you need.</p>
                </div>
            <?php else: ?>
                <div class="row g-3" id="roleWorkspaceGrid" data-ts-workspace-list>
                    <?php foreach ($accessibleModules as $module): ?>
                        <div class="col-md-6 col-lg-4" data-ts-workspace-item data-search-text="<?= e(strtolower($module['label'])) ?>">
                            <a href="<?= url($module['href']) ?>" class="card h-100 text-decoration-none dashboard-module-card">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <span class="module-icon" aria-hidden="true"><i class="bi <?= e($module['icon']) ?>"></i></span>
                                    <span class="dashboard-module-copy">
                                        <span class="dashboard-module-label"><?= e($module['label']) ?></span>
                                        <span class="dashboard-module-hint">Open section</span>
                                    </span>
                                    <span class="dashboard-module-arrow" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted text-center py-3" data-ts-workspace-empty hidden>No modules match your search.</div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
};
?>
