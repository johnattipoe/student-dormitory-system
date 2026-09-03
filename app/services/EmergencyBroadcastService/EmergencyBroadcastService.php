<?php

namespace App\Services;

class EmergencyBroadcastService
{
    private FirebaseService $firebase;

    private string $collection = 'emergency_broadcasts';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            $broadcasts = $this->firebase->getCollection($this->collection, [], 500);
            usort($broadcasts, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));
            return $broadcasts;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(string $message, array $recipients, ?string $studentId = null, ?string $createdBy = null): array
    {
        $message = trim($message);
        $recipients = array_values(array_intersect($recipients, ['house', 'parents', 'clinical']));
        if ($message === '') {
            return ['success' => false, 'message' => 'Please enter a broadcast message.'];
        }
        if ($recipients === []) {
            $recipients = ['house', 'parents', 'clinical'];
        }

        try {
            $student = $studentId !== null && $studentId !== '' ? StudentService::find($studentId) : null;
            if (in_array('parents', $recipients, true) && !$student) {
                return ['success' => false, 'message' => 'Select a student before sending a parent broadcast.'];
            }

            $id = $this->firebase->addDocument($this->collection, [
                'message' => $message,
                'recipients' => $recipients,
                'studentId' => $studentId,
                'createdBy' => $createdBy,
            ]);

            $notificationService = new NotificationService();
            $sent = 0;
            $roles = [];
            if (in_array('house', $recipients, true)) {
                $roles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
            }
            if (in_array('clinical', $recipients, true)) {
                $roles[] = ROLE_NURSE;
            }
            foreach (array_unique($roles) as $role) {
                $result = $notificationService->create([
                    'recipientType' => 'role',
                    'role' => $role,
                    'title' => 'Emergency clinical broadcast',
                    'message' => $message,
                    'type' => 'danger',
                    'isUrgent' => true,
                ]);
                $sent += (int) ($result['count'] ?? 0);
            }
            $notificationStatus = $sent > 0 ? 'sent' : 'no_recipients';

            $parentStatus = 'not_selected';
            if (in_array('parents', $recipients, true)) {
                $guardianPhone = trim((string) ($student['guardianPhone'] ?? $student['parentPhone'] ?? ''));
                if ($guardianPhone === '') {
                    $parentStatus = 'no_phone';
                } else {
                    try {
                        $smsService = new BmsSmsService();
                        $smsResult = $smsService->send($guardianPhone, $message);
                        $parentStatus = $smsResult['success'] ? 'sent' : 'failed';
                    } catch (\Throwable $e) {
                        $parentStatus = 'failed';
                    }
                }
                $this->firebase->updateDocument($this->collection, $id, [
                    'notificationCount' => $sent,
                    'notificationStatus' => $notificationStatus,
                    'parentSmsStatus' => $parentStatus,
                ]);
            } else {
                $this->firebase->updateDocument($this->collection, $id, [
                    'notificationCount' => $sent,
                    'notificationStatus' => $notificationStatus,
                ]);
            }

            $parentMessage = $parentStatus === 'sent' ? ' Parent SMS sent.' : ($parentStatus === 'no_phone' ? ' No guardian phone was found.' : '');
            return ['success' => true, 'message' => 'Emergency broadcast queued for ' . $sent . ' recipient(s).' . $parentMessage, 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to queue emergency broadcast.'];
        }
    }
}
