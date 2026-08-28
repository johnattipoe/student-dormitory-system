<?php

namespace App\Services;

class ExeatService
{
    private FirebaseService $firebase;

    private string $collection = 'exeats';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            $records = $this->firebase->getCollection($this->collection, [], 1000);
            usort($records, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));

            return $records;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(?string $id): ?array
    {
        if (!$id) {
            return null;
        }

        try {
            return $this->firebase->getDocument($this->collection, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function create(array $data): array
    {
        $studentId = trim((string) ($data['studentId'] ?? ''));
        $exeatType = strtolower(trim((string) ($data['exeatType'] ?? $data['type'] ?? 'external')));
        if (!in_array($exeatType, ['internal', 'external'], true)) {
            $exeatType = 'external';
        }

        $startDate = trim((string) ($data['startDate'] ?? $data['date'] ?? ''));
        $endDate = trim((string) ($data['endDate'] ?? $startDate));
        $startTime = trim((string) ($data['startTime'] ?? ''));
        $closeTime = trim((string) ($data['closeTime'] ?? $data['endTime'] ?? ''));
        $destination = trim((string) ($data['destination'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($studentId === '') {
            return ['success' => false, 'message' => 'Student profile was not found for this account.'];
        }

        if ($exeatType === 'internal') {
            if ($startDate === '' || $startTime === '' || $closeTime === '' || $reason === '') {
                return ['success' => false, 'message' => 'Date, start time, close time, and reason are required for internal exeat.'];
            }
            $endDate = $startDate;
        } else {
            if ($startDate === '' || $endDate === '' || $reason === '') {
                return ['success' => false, 'message' => 'Start date, end date, and reason are required for external exeat.'];
            }
            if ($endDate < $startDate) {
                return ['success' => false, 'message' => 'End date cannot be before the start date.'];
            }
        }

        try {
            $student = StudentService::find($studentId) ?? [];
            $id = $this->firebase->addDocument($this->collection, [
                'studentId' => $studentId,
                'studentName' => $data['studentName'] ?? $this->studentName($student),
                'houseId' => $data['houseId'] ?? $student['houseId'] ?? null,
                'roomId' => $data['roomId'] ?? $student['roomId'] ?? null,
                'exeatType' => $exeatType,
                'type' => $exeatType,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'startTime' => $startTime,
                'closeTime' => $closeTime,
                'endTime' => $closeTime,
                'destination' => $destination,
                'reason' => $reason,
                'guardianPhone' => $data['guardianPhone'] ?? $student['guardianPhone'] ?? '',
                'status' => 'pending',
                'requestedBy' => $data['requestedBy'] ?? null,
                'createdByRole' => $data['createdByRole'] ?? null,
                'createdAt' => date(DATE_ATOM),
            ]);

            $typeLabel = $exeatType === 'internal' ? 'Internal' : 'External';
            return ['success' => true, 'message' => $typeLabel . ' exeat request submitted successfully.', 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to submit exeat request: ' . $e->getMessage()];
        }
    }

    public function update(string $id, array $data): array
    {
        if ($id === '') {
            return ['success' => false, 'message' => 'Missing exeat record reference.'];
        }

        $record = $this->find($id);
        if (!$record) {
            return ['success' => false, 'message' => 'Exeat request was not found.'];
        }

        $exeatType = strtolower(trim((string) ($data['exeatType'] ?? $data['type'] ?? $record['exeatType'] ?? 'external')));
        if (!in_array($exeatType, ['internal', 'external'], true)) {
            $exeatType = 'external';
        }

        $startDate = trim((string) ($data['startDate'] ?? $data['date'] ?? $record['startDate'] ?? ''));
        $endDate = trim((string) ($data['endDate'] ?? $record['endDate'] ?? $startDate));
        $startTime = trim((string) ($data['startTime'] ?? $record['startTime'] ?? ''));
        $closeTime = trim((string) ($data['closeTime'] ?? $data['endTime'] ?? $record['closeTime'] ?? ''));
        $destination = trim((string) ($data['destination'] ?? $record['destination'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? $record['reason'] ?? ''));

        if ($exeatType === 'internal') {
            if ($startDate === '' || $startTime === '' || $closeTime === '' || $reason === '') {
                return ['success' => false, 'message' => 'Date, start time, close time, and reason are required for internal exeat.'];
            }
            $endDate = $startDate;
        } else {
            if ($startDate === '' || $endDate === '' || $reason === '') {
                return ['success' => false, 'message' => 'Start date, end date, and reason are required for external exeat.'];
            }
            if ($endDate < $startDate) {
                return ['success' => false, 'message' => 'End date cannot be before the start date.'];
            }
        }

        $payload = [
            'exeatType' => $exeatType,
            'type' => $exeatType,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'startTime' => $startTime,
            'closeTime' => $closeTime,
            'endTime' => $closeTime,
            'destination' => $destination,
            'reason' => $reason,
            'updatedAt' => date(DATE_ATOM),
        ];

        if (!empty($data['status'])) {
            $payload['status'] = strtolower(trim((string) $data['status']));
        }

        try {
            $this->firebase->updateDocument($this->collection, $id, $payload);
            return ['success' => true, 'message' => 'Exeat request updated successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to update exeat request: ' . $e->getMessage()];
        }
    }

    public function delete(string $id): array
    {
        if ($id === '') {
            return ['success' => false, 'message' => 'Missing exeat record reference.'];
        }

        try {
            $this->firebase->deleteDocument($this->collection, $id);
            return ['success' => true, 'message' => 'Exeat request deleted successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to delete exeat request: ' . $e->getMessage()];
        }
    }

    public function updateStatus(string $id, string $action, ?string $actorId = null): array
    {
        $statusMap = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'depart' => 'departed',
            'return' => 'returned',
        ];

        if ($id === '') {
            return ['success' => false, 'message' => 'Missing exeat record reference.'];
        }
        if (!isset($statusMap[$action])) {
            return ['success' => false, 'message' => 'Invalid exeat action.'];
        }

        $record = $this->find($id);
        if (!$record) {
            return ['success' => false, 'message' => 'Exeat request was not found.'];
        }

        $currentStatus = strtolower((string) ($record['status'] ?? 'pending'));
        $allowedTransitions = [
            'approve' => ['pending'],
            'reject' => ['pending'],
            'depart' => ['approved'],
            'return' => ['departed'],
        ];
        if (!in_array($currentStatus, $allowedTransitions[$action], true)) {
            return ['success' => false, 'message' => 'This request cannot be changed from ' . $currentStatus . ' using that action.'];
        }

        try {
            $newStatus = $statusMap[$action];
            $timestampField = match ($newStatus) {
                'approved', 'rejected' => 'reviewedAt',
                'departed' => 'departedAt',
                'returned' => 'returnedAt',
                default => 'updatedAt',
            };
            $actorField = match ($newStatus) {
                'approved', 'rejected' => 'reviewedBy',
                'departed' => 'departedBy',
                'returned' => 'returnedBy',
                default => 'updatedBy',
            };

            $this->firebase->updateDocument($this->collection, $id, [
                'status' => $newStatus,
                $timestampField => date(DATE_ATOM),
                $actorField => $actorId,
            ]);

            return ['success' => true, 'message' => 'Exeat request marked as ' . $newStatus . '.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Unable to update exeat request: ' . $e->getMessage()];
        }
    }

    public function visibleForRole(string $role, ?string $userId, ?string $houseId, ?string $studentId): array
    {
        $records = $this->all();

        if ($role === \ROLE_STUDENT) {
            return array_values(array_filter($records, static function (array $record) use ($studentId, $userId): bool {
                $recordStudentId = (string) ($record['studentId'] ?? '');
                $recordRequestedBy = (string) ($record['requestedBy'] ?? '');
                return ($studentId && $recordStudentId === (string) $studentId)
                    || ($userId && ($recordStudentId === (string) $userId || $recordRequestedBy === (string) $userId));
            }));
        }

        if ($role === \ROLE_SENIOR_HOUSEPARENT && !$houseId) {
            return $records;
        }

        if (in_array($role, [\ROLE_HOUSE_MASTER, \ROLE_HOUSE_MISTRESS, \ROLE_SENIOR_HOUSEPARENT], true)) {
            if (!$houseId) {
                return $records;
            }

            try {
                $houseStudentIds = [];
                foreach (StudentService::all($houseId) as $student) {
                    $studentIdKey = (string) ($student['id'] ?? '');
                    if ($studentIdKey !== '') {
                        $houseStudentIds[$studentIdKey] = true;
                    }
                }
            } catch (\Throwable $e) {
                $houseStudentIds = [];
            }

            return array_values(array_filter($records, static function (array $record) use ($houseId, $houseStudentIds): bool {
                $recordHouseId = (string) ($record['houseId'] ?? '');
                $recordStudentId = (string) ($record['studentId'] ?? '');
                return $recordHouseId === (string) $houseId || isset($houseStudentIds[$recordStudentId]);
            }));
        }

        return $records;
    }

    public function studentForUser(array $user): ?array
    {
        $candidates = array_filter([
            (string) ($user['studentId'] ?? ''),
            (string) ($user['uid'] ?? ''),
            (string) ($user['id'] ?? ''),
        ]);

        foreach ($candidates as $candidate) {
            try {
                $student = StudentService::find($candidate);
                if ($student) {
                    return $student;
                }
            } catch (\Throwable $e) {
                // Try the next identifier.
            }
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email !== '') {
            try {
                foreach (StudentService::all() as $student) {
                    if (strtolower(trim((string) ($student['email'] ?? ''))) === $email) {
                        return $student;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and fallback
            }
        }

        if (!empty($user['role']) && $user['role'] === \ROLE_STUDENT) {
            $firstName = $user['firstName'] ?? $user['name'] ?? 'Student';
            $lastName = $user['lastName'] ?? '';
            return [
                'id' => (string) ($user['uid'] ?? $user['id'] ?? ''),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => trim($firstName . ' ' . $lastName),
                'admissionNo' => $user['admissionNo'] ?? $user['username'] ?? $user['email'] ?? '',
                'houseId' => $user['houseId'] ?? $user['house_id'] ?? null,
                'roomId' => $user['roomId'] ?? $user['room_id'] ?? null,
                'guardianPhone' => $user['guardianPhone'] ?? $user['phone'] ?? '',
            ];
        }

        return null;
    }

    public function statusCounts(array $records): array
    {
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'departed' => 0, 'returned' => 0];
        foreach ($records as $record) {
            $status = strtolower((string) ($record['status'] ?? 'pending'));
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }

        return $counts;
    }

    private function studentName(array $student): string
    {
        $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
        return $name !== '' ? $name : (string) ($student['name'] ?? 'Unnamed student');
    }
}
