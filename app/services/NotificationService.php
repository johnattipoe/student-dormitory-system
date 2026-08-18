<?php

namespace App\Services;

class NotificationService
{
    private FirebaseService $firebase;

    private string $collection = 'notifications';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            return $this->firebase->getCollection(
                $this->collection
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function forUser(?string $uid): array
    {
        if (!$uid) {
            return [];
        }

        try {
            return $this->firebase->getCollection(
                $this->collection,
                [
                    ['userId', '=', $uid]
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data): array
    {
        try {
            $settings = require __DIR__ . '/../config/app.php';
            if (empty($settings['enable_notifications'])) {
                return [
                    'success' => false,
                    'message' => 'System notifications are disabled by the administrator.'
                ];
            }

            $recipientType = strtolower((string) ($data['recipientType'] ?? 'user'));
            $role = trim((string) ($data['role'] ?? ''));
            $userId = trim((string) ($data['userId'] ?? ''));

            if ($recipientType === 'all') {
                return $this->notifyAll($data);
            }

            if ($recipientType === 'role') {
                if ($role === '') {
                    return [
                        'success' => false,
                        'message' => 'Role is required.'
                    ];
                }
                return $this->notifyRole($role, $data);
            }

            if ($userId === '') {
                return [
                    'success' => false,
                    'message' => 'User is required.'
                ];
            }

            // Check user preferences before creating notification
            $preferenceService = new PreferenceService();
            $notificationType = $data['type'] ?? 'info';
            $isUrgent = ($data['isUrgent'] ?? false) === true;
            
            // Skip notification if user disabled this type and it's not urgent
            if (!$isUrgent && !$preferenceService->isNotificationTypeEnabled($userId, $notificationType)) {
                return [
                    'success' => false,
                    'message' => 'Notification type disabled for user.',
                    'skipped' => true
                ];
            }

            $id = $this->firebase->addDocument(
                $this->collection,
                [
                    'userId' => $userId,
                    'title' => $data['title'] ?? '',
                    'message' => $data['message'] ?? '',
                    'type' => $notificationType,
                    'read' => false,
                    'inQuietHours' => $preferenceService->isInQuietHours($userId),
                    'createdAt' => (new \DateTime())->format(DATE_ATOM),
                ]
            );

            return [
                'success' => true,
                'message' => 'Notification created.',
                'id' => $id
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to create notification.'
            ];
        }
    }

    public function notifyRole(string $role, array $data): array
    {
        $users = (new UserService())->byRole($role);
        if (empty($users)) {
            return [
                'success' => false,
                'message' => 'No users found for that role.'
            ];
        }

        $count = 0;
        $preferenceService = new PreferenceService();
        $notificationType = $data['type'] ?? 'info';
        $isUrgent = ($data['isUrgent'] ?? false) === true;

        foreach ($users as $user) {
            $uid = (string) ($user['uid'] ?? $user['id'] ?? '');
            if ($uid === '') {
                continue;
            }

            // Skip if user disabled this type and it's not urgent
            if (!$isUrgent && !$preferenceService->isNotificationTypeEnabled($uid, $notificationType)) {
                continue;
            }

            $this->firebase->addDocument(
                $this->collection,
                [
                    'userId' => $uid,
                    'title' => $data['title'] ?? '',
                    'message' => $data['message'] ?? '',
                    'type' => $notificationType,
                    'read' => false,
                    'inQuietHours' => $preferenceService->isInQuietHours($uid),
                    'createdAt' => (new \DateTime())->format(DATE_ATOM),
                ]
            );
            $count++;
        }

        return [
            'success' => true,
            'message' => 'Notification sent to ' . $count . ' ' . $role . ' user(s).',
            'count' => $count,
        ];
    }

    public function notifyAll(array $data): array
    {
        $users = (new UserService())->all();
        $count = 0;
        $preferenceService = new PreferenceService();
        $notificationType = $data['type'] ?? 'info';
        $isUrgent = ($data['isUrgent'] ?? false) === true;

        foreach ($users as $user) {
            $uid = (string) ($user['uid'] ?? $user['id'] ?? '');
            if ($uid === '') {
                continue;
            }

            // Skip if user disabled this type and it's not urgent
            if (!$isUrgent && !$preferenceService->isNotificationTypeEnabled($uid, $notificationType)) {
                continue;
            }

            $this->firebase->addDocument(
                $this->collection,
                [
                    'userId' => $uid,
                    'title' => $data['title'] ?? '',
                    'message' => $data['message'] ?? '',
                    'type' => $notificationType,
                    'read' => false,
                    'inQuietHours' => $preferenceService->isInQuietHours($uid),
                    'createdAt' => (new \DateTime())->format(DATE_ATOM),
                ]
            );
            $count++;
        }

        return [
            'success' => true,
            'message' => 'Notification sent to all ' . $count . ' user(s).',
            'count' => $count,
        ];
    }

    public function markAsReadById(string $id): array
    {
        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Notification ID is required.'
            ];
        }

        try {
            $this->firebase->updateDocument(
                $this->collection,
                $id,
                ['read' => true]
            );

            return [
                'success' => true,
                'message' => 'Notification marked as read.'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to update notification.'
            ];
        }
    }

    public function unreadCount(?string $uid = null): int
    {
        $notifications = $uid ? $this->forUser($uid) : $this->all();
        $count = 0;
        foreach ($notifications as $notification) {
            if (empty($notification['read'])) {
                $count++;
            }
        }

        return $count;
    }

    public function markAsRead(
        string $id,
        ?string $uid
    ): array {
        try {

            $notification = $this->firebase->getDocument(
                $this->collection,
                $id
            );

            if (
                !$notification ||
                ($notification['userId'] ?? '') !== $uid
            ) {
                return [
                    'success' => false,
                    'message' => 'Notification not found.'
                ];
            }

            $this->firebase->updateDocument(
                $this->collection,
                $id,
                [
                    'read' => true
                ]
            );

            return [
                'success' => true,
                'message' => 'Notification marked as read.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to update notification.'
            ];
        }
    }

    public function markAllAsRead(
        ?string $uid
    ): array {
        if (!$uid) {
            return [
                'success' => false,
                'message' => 'User not found.'
            ];
        }

        $notifications = $this->forUser($uid);

        foreach ($notifications as $notification) {

            if (!empty($notification['id'])) {

                $this->firebase->updateDocument(
                    $this->collection,
                    $notification['id'],
                    [
                        'read' => true
                    ]
                );
            }
        }

        return [
            'success' => true,
            'message' => 'All notifications marked as read.'
        ];
    }
}