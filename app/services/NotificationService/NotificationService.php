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

    public function all(int $limit = 0): array
    {
        try {
            if ($limit > 0) {
                return $this->firebase->getCollection(
                    $this->collection,
                    [],
                    $limit
                );
            }

            return $this->firebase->getCollection(
                $this->collection
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function count(): int
    {
        try {
            return $this->firebase->count($this->collection);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function forUser(?string $uid, int $limit = 0): array
    {
        if (!$uid) {
            return [];
        }

        try {
            $identifiers = array_values(array_unique($this->userIdentifiers($uid)));
            if ($identifiers === []) {
                return [];
            }

            $notifications = [];
            foreach (array_chunk($identifiers, 10) as $identifierBatch) {
                $batchLimit = $limit > 0 ? $limit : 150;
                foreach ($this->firebase->getCollection($this->collection, [['userId', 'in', $identifierBatch]], $batchLimit) as $notification) {
                    $notificationId = (string) ($notification['id'] ?? '');
                    if ($notificationId !== '') {
                        $notifications[$notificationId] = $notification;
                    }
                }
            }

            usort($notifications, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));

            return array_values($notifications);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function userIdentifiers(string $identifier): array
    {
        $identifiers = [trim($identifier)];
        try {
            $user = (new UserService())->find($identifier);
            if ($user) {
                foreach ([$user['id'] ?? '', $user['uid'] ?? ''] as $userIdentifier) {
                    if ($userIdentifier !== '' && !in_array((string) $userIdentifier, $identifiers, true)) {
                        $identifiers[] = (string) $userIdentifier;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep the session identifier as a fallback.
        }
        return array_values(array_filter($identifiers));
    }

    public function findForAdmin(string $id): ?array
    {
        if ($id === '') {
            return null;
        }

        try {
            return $this->firebase->getDocument($this->collection, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function findForUser(string $id, ?string $uid): ?array
    {
        if ($id === '' || !$uid) {
            return null;
        }

        try {
            $notification = $this->firebase->getDocument($this->collection, $id);
            if (!$notification || !in_array((string) ($notification['userId'] ?? ''), $this->userIdentifiers((string) $uid), true)) {
                return null;
            }

            return $notification;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function updateForUser(string $id, ?string $uid, array $data): array
    {
        if (!$this->findForUser($id, $uid)) {
            return ['success' => false, 'message' => 'Notification not found.'];
        }

        return $this->update($id, $data);
    }

    public function deleteForUser(string $id, ?string $uid): array
    {
        if (!$this->findForUser($id, $uid)) {
            return ['success' => false, 'message' => 'Notification not found.'];
        }

        return $this->delete($id);
    }

    public function create(array $data): array
    {
        try {
            $settings = require __DIR__ . '/../../config/app/app.php';
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

    public function update(string $id, array $data): array
    {
        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Notification ID is required.'
            ];
        }

        try {
            $this->firebase->updateDocument($this->collection, $id, [
                'title' => $data['title'] ?? '',
                'message' => $data['message'] ?? '',
                'type' => $data['type'] ?? 'info',
            ]);

            return [
                'success' => true,
                'message' => 'Notification updated successfully.'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to update notification.'
            ];
        }
    }

    public function delete(string $id): array
    {
        if ($id === '') {
            return [
                'success' => false,
                'message' => 'Notification ID is required.'
            ];
        }

        try {
            $this->firebase->deleteDocument($this->collection, $id);

            return [
                'success' => true,
                'message' => 'Notification deleted successfully.'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to delete notification.'
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
        try {
            if ($uid) {
                return $this->firebase->count(
                    $this->collection,
                    [
                        ['userId', '=', $uid],
                        ['read', '=', false],
                    ]
                );
            }

            return $this->firebase->count(
                $this->collection,
                [['read', '=', false]]
            );
        } catch (\Throwable $e) {
            return 0;
        }
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