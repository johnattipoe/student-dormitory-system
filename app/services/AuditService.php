<?php

namespace App\Services;

use App\Services\FirebaseService;

/**
 * AuditService - Tracks changes to medical records and other sensitive operations
 */
class AuditService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    /**
     * Log a medical record change (severity update, flag status, etc.)
     */
    public function logMedicalRecordChange(
        string $recordId,
        string $studentId,
        string $action,
        ?string $changedBy,
        array $changes = [],
        ?string $reason = null
    ): bool {
        try {
            $auditData = [
                'recordId' => $recordId,
                'studentId' => $studentId,
                'action' => $action,
                'changedBy' => $changedBy ?? 'system',
                'changes' => $changes,
                'reason' => $reason ?? '',
                'timestamp' => date('Y-m-d H:i:s'),
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];

            $this->firebase->addDocument('medical_record_audits', $auditData);
            return true;
        } catch (\Throwable $e) {
            error_log('Audit logging failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit history for a medical record
     */
    public function getMedicalRecordHistory(string $recordId): array
    {
        try {
            $audits = $this->firebase->where('medical_record_audits', 'recordId', '=', $recordId);
            
            // Sort by timestamp descending
            usort($audits, function($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? '');
                $timeB = strtotime($b['timestamp'] ?? '');
                return $timeB - $timeA;
            });

            return $audits;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get audit history for a student's medical records
     */
    public function getStudentMedicalHistory(string $studentId): array
    {
        try {
            $audits = $this->firebase->where('medical_record_audits', 'studentId', '=', $studentId);
            
            // Sort by timestamp descending
            usort($audits, function($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? '');
                $timeB = strtotime($b['timestamp'] ?? '');
                return $timeB - $timeA;
            });

            return $audits;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get all audit entries with optional filtering
     */
    public function all(?string $action = null, ?int $limit = 1000): array
    {
        try {
            $audits = $this->firebase->getCollection('medical_record_audits', [], $limit);

            if ($action) {
                $audits = array_filter($audits, fn($a) => ($a['action'] ?? '') === $action);
            }

            // Sort by timestamp descending
            usort($audits, function($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? '');
                $timeB = strtotime($b['timestamp'] ?? '');
                return $timeB - $timeA;
            });

            return $audits;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get severity change history for a record
     */
    public function getSeverityChanges(string $recordId): array
    {
        $audits = $this->getMedicalRecordHistory($recordId);
        
        $severityChanges = [];
        foreach ($audits as $audit) {
            $changes = $audit['changes'] ?? [];
            if (isset($changes['severity'])) {
                $severityChanges[] = [
                    'timestamp' => $audit['timestamp'] ?? '',
                    'changedBy' => $audit['changedBy'] ?? 'unknown',
                    'from' => $changes['severity']['from'] ?? '—',
                    'to' => $changes['severity']['to'] ?? '—',
                    'reason' => $audit['reason'] ?? '',
                ];
            }
        }

        return $severityChanges;
    }

    /**
     * Format change data for display
     */
    public static function formatChanges(array $changes): string
    {
        $parts = [];
        foreach ($changes as $field => $change) {
            if (is_array($change) && isset($change['from']) && isset($change['to'])) {
                $parts[] = "$field: " . $change['from'] . " → " . $change['to'];
            }
        }
        return implode(', ', $parts);
    }
}
