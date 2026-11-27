<?php
if (!defined('APP_ROOT')) {
    require dirname(__DIR__, 2) . '/bootstrap.php';
}

require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Middleware\RoleMiddleware;
use App\Services\NotificationService;

RoleMiddleware::allow([ROLE_ADMIN]);

$unreadCount = (new NotificationService())->unreadCount();

json_success('Notification count', ['count' => $unreadCount]);
