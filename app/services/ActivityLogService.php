<?php

namespace App\Services;

class ActivityLogService
{
    public static function log(string $userId, string $action, string $description = '', array $meta = []): void
    {
        try {
            $timestamp = (new \DateTime())->format(DATE_ATOM);

            FirebaseService::getInstance()->addDocument(\COL_ACTIVITY_LOGS, [
                'userId' => $userId,
                'user' => $userId,
                'action' => $action,
                'event' => $action,
                'description' => $description,
                'meta' => $meta,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'timestamp' => $timestamp,
            ]);
        } catch (\Throwable $e) {
            error_log('ActivityLog failed: ' . $e->getMessage());
        }
    }
}
