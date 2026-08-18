<?php

namespace App\Services;

use App\Services\FirebaseService;
use App\Services\NotificationService;

/**
 * VisitorAlertService - Monitors visitor durations and creates alerts for overstays
 */
class VisitorAlertService
{
    private FirebaseService $firebase;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
        $this->notificationService = new NotificationService();
    }

    /**
     * Check for visitor overstays and create alerts
     * Typically called periodically (every 30 minutes)
     */
    public function checkForOvrstays(?int $thresholdHours = 2): array
    {
        $alerts = [];
        
        try {
            $visitors = $this->firebase->where(COL_VISITORS, 'status', '=', 'inside');
            
            foreach ($visitors as $visitor) {
                $checkInTime = strtotime($visitor['checkInTime'] ?? '');
                if (!$checkInTime) continue;

                $duration = (time() - $checkInTime) / 3600; // Convert to hours

                if ($duration >= $thresholdHours) {
                    $alert = $this->createOvrstayAlert($visitor, $duration);
                    $alerts[] = $alert;
                }
            }
        } catch (\Throwable $e) {
            error_log('Overstay check failed: ' . $e->getMessage());
        }

        return $alerts;
    }

    /**
     * Create overstay alert and notify security
     */
    private function createOvrstayAlert(array $visitor, float $duration): array
    {
        $visitorName = $visitor['visitorName'] ?? 'Unknown';
        $studentId = $visitor['studentId'] ?? '';
        $durationFormatted = $this->formatDuration($duration);

        $alertData = [
            'visitorId' => $visitor['id'] ?? '',
            'visitorName' => $visitorName,
            'studentId' => $studentId,
            'duration' => $duration,
            'durationFormatted' => $durationFormatted,
            'checkInTime' => $visitor['checkInTime'] ?? '',
            'alertSentAt' => date('Y-m-d H:i:s'),
            'severity' => $duration >= 4 ? 'high' : 'medium',
        ];

        try {
            // Save alert to database
            $this->firebase->addDocument('visitor_overstay_alerts', $alertData);

            // Notify security role
            $this->notificationService->create([
                'recipientType' => 'role',
                'role' => ROLE_SECURITY,
                'title' => '⚠️ Visitor Overstay Alert',
                'message' => "$visitorName has been inside for $durationFormatted",
                'type' => 'warning',
                'isUrgent' => true, // Override quiet hours
                'relatedVisitorId' => $visitor['id'] ?? '',
            ]);

            // Notify house master if applicable
            if ($studentId) {
                try {
                    $student = (new StudentService())->find($studentId);
                    $houseId = $student['houseId'] ?? '';
                    
                    if ($houseId) {
                        // Get house masters for this house
                        $houseMasters = $this->firebase->where(COL_USERS, 'houseId', '=', $houseId);
                        $houseMasters = array_filter($houseMasters, fn($u) => in_array(($u['role'] ?? ''), [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS]));
                        
                        foreach ($houseMasters as $hm) {
                            $this->notificationService->create([
                                'userId' => $hm['uid'] ?? $hm['id'] ?? '',
                                'title' => '⚠️ Visitor Overstay Alert',
                                'message' => "$visitorName (visiting {$student['firstName']}) has been inside for $durationFormatted",
                                'type' => 'warning',
                                'isUrgent' => true,
                                'relatedVisitorId' => $visitor['id'] ?? '',
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    // Silently fail if house master notification fails
                }
            }
        } catch (\Throwable $e) {
            error_log('Failed to create overstay alert: ' . $e->getMessage());
        }

        return $alertData;
    }

    /**
     * Get pending overstay alerts
     */
    public function getPendingAlerts(): array
    {
        try {
            $alerts = $this->firebase->getCollection('visitor_overstay_alerts', [], 100);
            
            // Filter to alerts from last 24 hours
            $cutoff = time() - (24 * 3600);
            $alerts = array_filter($alerts, function($a) use ($cutoff) {
                $time = strtotime($a['alertSentAt'] ?? '');
                return $time >= $cutoff;
            });

            // Sort by alert time descending
            usort($alerts, function($a, $b) {
                $timeA = strtotime($a['alertSentAt'] ?? '');
                $timeB = strtotime($b['alertSentAt'] ?? '');
                return $timeB - $timeA;
            });

            return $alerts;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Format duration for display
     */
    private function formatDuration(float $hours): string
    {
        $wholeHours = floor($hours);
        $minutes = round(($hours - $wholeHours) * 60);

        if ($wholeHours === 0) {
            return "$minutes minute" . ($minutes !== 1 ? 's' : '');
        }

        $hourText = $wholeHours . " hour" . ($wholeHours !== 1 ? 's' : '');
        
        if ($minutes === 0) {
            return $hourText;
        }

        return "$hourText and $minutes minute" . ($minutes !== 1 ? 's' : '');
    }

    /**
     * Resolve an overstay alert (when visitor checks out)
     */
    public function resolveAlert(string $visitorId): bool
    {
        try {
            $alerts = $this->firebase->where('visitor_overstay_alerts', 'visitorId', '=', $visitorId);
            
            foreach ($alerts as $alert) {
                $alertId = $alert['id'] ?? '';
                if ($alertId) {
                    $this->firebase->updateDocument('visitor_overstay_alerts', $alertId, [
                        'resolvedAt' => date('Y-m-d H:i:s'),
                        'status' => 'resolved',
                    ]);
                }
            }
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
