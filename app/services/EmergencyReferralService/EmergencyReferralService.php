<?php

namespace App\Services;

class EmergencyReferralService
{
    private FirebaseService $firebase;

    private string $collection = 'emergency_referrals';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            $referrals = $this->firebase->getCollection($this->collection, [], 500);
            usort($referrals, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));
            return $referrals;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function create(array $data): array
    {
        $studentId = trim((string) ($data['studentId'] ?? ''));
        $facility = trim((string) ($data['facility'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($studentId === '' || $facility === '' || $reason === '') {
            return ['success' => false, 'message' => 'Student, facility, and reason are required.'];
        }

        try {
            $id = $this->firebase->addDocument($this->collection, [
                'studentId' => $studentId,
                'facility' => $facility,
                'reason' => $reason,
                'doctor' => trim((string) ($data['doctor'] ?? '')),
                'urgency' => trim((string) ($data['urgency'] ?? 'urgent')) ?: 'urgent',
                'notes' => trim((string) ($data['notes'] ?? '')),
                'createdBy' => $data['createdBy'] ?? null,
                'createdAt' => date(DATE_ATOM),
            ]);
            return ['success' => true, 'message' => 'Referral created successfully.', 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to save referral.'];
        }
    }
}
