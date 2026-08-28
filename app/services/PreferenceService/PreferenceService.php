<?php

namespace App\Services;

/**
 * PreferenceService - Manages user notification and system preferences
 * Reads from 'notification_preferences' collection
 */
class PreferenceService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    /**
     * Get preferences for a user
     */
    public function getForUser(?string $userId): array
    {
        if (!$userId) {
            return $this->defaultPreferences();
        }

        try {
            $prefs = $this->firebase->where('notification_preferences', 'userId', '=', $userId);
            if (!empty($prefs)) {
                return array_merge($this->defaultPreferences(), $prefs[0]);
            }
        } catch (\Throwable $e) {
            // Return defaults if query fails
        }

        return $this->defaultPreferences();
    }

    /**
     * Check if user is in quiet hours
     */
    public function isInQuietHours(?string $userId): bool
    {
        $prefs = $this->getForUser($userId);
        
        if (empty($prefs['quietHours'])) {
            return false;
        }

        $quietHours = $prefs['quietHours'];
        $currentTime = date('H:i');

        // Simple check - assumes quiet hours don't span midnight
        // Format: "22:00" or "22:00-08:00" would need advanced parsing
        if (strpos($quietHours, '-') !== false) {
            list($start, $end) = explode('-', $quietHours);
            $start = trim($start);
            $end = trim($end);

            if ($start < $end) {
                // Same day range (e.g., 09:00-17:00)
                return $currentTime >= $start && $currentTime <= $end;
            } else {
                // Overnight range (e.g., 22:00-08:00)
                return $currentTime >= $start || $currentTime <= $end;
            }
        }

        return false;
    }

    /**
     * Check if notification type is enabled for user
     */
    public function isNotificationTypeEnabled(?string $userId, string $type): bool
    {
        $prefs = $this->getForUser($userId);

        // Map notification types to preference keys
        $typeMap = [
            'email' => 'emailNotifications',
            'attendance' => 'attendanceAlerts',
            'visitor' => 'visitorUpdates',
            'incident' => 'incidentAlerts',
            'medical' => 'medicalAlerts',
            'system' => 'systemNotifications',
        ];

        $key = $typeMap[$type] ?? 'systemNotifications';
        return (bool) ($prefs[$key] ?? true);
    }

    /**
     * Get notification frequency for user
     */
    public function getFrequency(?string $userId): string
    {
        $prefs = $this->getForUser($userId);
        return $prefs['notificationFrequency'] ?? 'immediate';
    }

    /**
     * Save preferences for user
     */
    public function save(string $userId, array $data): bool
    {
        try {
            $existing = $this->firebase->where('notification_preferences', 'userId', '=', $userId);
            
            $saveData = array_merge($data, [
                'updatedAt' => date('Y-m-d H:i:s'),
            ]);

            if (!empty($existing)) {
                $prefId = $existing[0]['id'] ?? '';
                $this->firebase->updateDocument('notification_preferences', $prefId, $saveData);
            } else {
                $saveData['userId'] = $userId;
                $saveData['createdAt'] = date('Y-m-d H:i:s');
                $this->firebase->addDocument('notification_preferences', $saveData);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Default preferences if none exist
     */
    private function defaultPreferences(): array
    {
        return [
            'userId' => null,
            'emailNotifications' => true,
            'attendanceAlerts' => true,
            'visitorUpdates' => true,
            'incidentAlerts' => true,
            'medicalAlerts' => true,
            'systemNotifications' => true,
            'quietHours' => '',
            'notificationFrequency' => 'immediate',
        ];
    }
}
