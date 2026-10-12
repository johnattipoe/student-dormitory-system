<?php
return static function (array $accessibleModules, array $recentActivity): void {
?>
<div class="col-xl-4">
    <section class="card border-0 shadow-sm h-100 permission-dashboard-activity">
        <div class="card-header permission-section-header">
            <div class="permission-section-title">
                <span class="permission-section-icon permission-section-icon-activity"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                <div>
                    <h2>Recent activity</h2>
                    <p>Latest system records visible to you.</p>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            <?php if (!isset($accessibleModules['activity_logs']) && !isset($accessibleModules['audit_trail'])): ?>
                <div class="permission-activity-empty">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <span>Activity log access is not assigned to your role.</span>
                </div>
            <?php elseif (!$recentActivity): ?>
                <div class="permission-activity-empty">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <span>No recent activity is available.</span>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush permission-activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <?php $activityText = (string) ($activity['action'] ?? $activity['description'] ?? 'Activity recorded'); ?>
                        <div class="list-group-item permission-activity-item">
                            <span class="permission-activity-marker" aria-hidden="true"><i class="bi bi-dot"></i></span>
                            <div class="permission-activity-copy">
                                <div class="permission-activity-text"><?= e($activityText) ?></div>
                                <div class="permission-activity-date"><i class="bi bi-clock" aria-hidden="true"></i><?= e((string) ($activity['createdAt'] ?? $activity['timestamp'] ?? 'Recently')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
};
?>
