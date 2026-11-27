<?php

namespace App\Services;

class MedicalService
{
    private FirebaseService $firebase;

    private string $collection = 'medical_records';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(int $limit = 150): array
    {
        try {
            $records = $this->firebase->getCollection(
                $this->collection,
                [],
                $limit
            );

            usort($records, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));

            return $records;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function recent(int $limit = 7): array
    {
        return $this->firebase->getCollection(
            $this->collection,
            [],
            max(1, min(50, $limit)),
            0,
            false,
            [['createdAt', 'DESC']]
        );
    }

    public function page(int $limit = 25, int $offset = 0): array
    {
        return $this->firebase->getCollection(
            $this->collection,
            [],
            max(1, min(150, $limit)),
            max(0, $offset),
            false,
            [['createdAt', 'DESC']]
        );
    }

    public function urgent(int $limit = 150): array
    {
        return $this->firebase->getCollection(
            $this->collection,
            [['severity', 'in', ['severe', 'critical', 'emergency']]],
            max(1, min(150, $limit)),
            0,
            false,
            [['createdAt', 'DESC']]
        );
    }

    public function filtered(array $wheres = [], int $limit = 150): array
    {
        $records = $this->firebase->getCollection(
            $this->collection,
            $wheres,
            max(1, min(150, $limit)),
            0,
            false,
            [['createdAt', 'DESC']]
        );

        usort($records, static fn(array $first, array $second): int => strcmp(
            (string) ($second['createdAt'] ?? ''),
            (string) ($first['createdAt'] ?? '')
        ));

        return $records;
    }

    public function forStudents(array $studentIds, int $limit = 150): array
    {
        $studentIds = array_values(array_unique(array_filter(array_map(
            static fn($studentId): string => trim((string) $studentId),
            $studentIds
        ))));
        if ($studentIds === []) {
            return [];
        }

        $records = [];
        foreach (array_chunk($studentIds, 30) as $studentIdBatch) {
            foreach ($this->firebase->getCollection(
                $this->collection,
                [['studentId', 'in', $studentIdBatch]],
                max(1, min(150, $limit)),
                0,
                false,
                [['createdAt', 'DESC']]
            ) as $record) {
                $records[(string) ($record['id'] ?? '')] = $record;
            }
        }

        return array_values($records);
    }

    public function countForStudents(array $studentIds, array $wheres = []): int
    {
        $studentIds = array_values(array_unique(array_filter(array_map(
            static fn($studentId): string => trim((string) $studentId),
            $studentIds
        ))));
        if ($studentIds === []) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($studentIds, 30) as $studentIdBatch) {
            $count += $this->firebase->count(
                $this->collection,
                array_merge([['studentId', 'in', $studentIdBatch]], $wheres)
            );
        }

        return $count;
    }

    public function countForStudentsBySeverity(array $studentIds, array $severities): int
    {
        $studentIds = array_values(array_unique(array_filter(array_map(
            static fn($studentId): string => trim((string) $studentId),
            $studentIds
        ))));
        $severities = array_values(array_unique(array_filter(array_map(
            static fn($severity): string => trim((string) $severity),
            $severities
        ))));
        if ($studentIds === [] || $severities === []) {
            return 0;
        }
        if (count($severities) > 30) {
            throw new \InvalidArgumentException('Severity filters exceed Firestore disjunction limits.');
        }

        $count = 0;
        $studentBatchSize = max(1, intdiv(30, count($severities)));
        foreach (array_chunk($studentIds, $studentBatchSize) as $studentIdBatch) {
            $count += $this->firebase->count($this->collection, [
                ['studentId', 'in', $studentIdBatch],
                ['severity', 'in', $severities],
            ]);
        }

        return $count;
    }

    public function find(?string $id): ?array
    {
        if (!$id) {
            return null;
        }

        try {
            return $this->firebase->getDocument(
                $this->collection,
                $id
            );
        } catch (\Throwable $e) {
            return null;
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

    public function countBySeverity(string $severity): int
    {
        return $this->firebase->count(
            $this->collection,
            [['severity', '=', $severity]]
        );
    }

    public function countCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return $this->firebase->count(
            $this->collection,
            [
                ['createdAt', '>=', $start->format(DATE_ATOM)],
                ['createdAt', '<', $end->format(DATE_ATOM)],
            ]
        );
    }

    public function todayCases(): int
    {
        try {
            $today = new \DateTimeImmutable('today');
            $tomorrow = $today->modify('+1 day');

            return $this->firebase->count(
                $this->collection,
                [
                    ['createdAt', '>=', $today->format(DATE_ATOM)],
                    ['createdAt', '<', $tomorrow->format(DATE_ATOM)],
                ]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function emergencyCases(): int
    {
        $count = 0;

        foreach (['severe', 'emergency', 'critical'] as $severity) {
            try {
                $count += $this->firebase->count(
                    $this->collection,
                    [['severity', '=', $severity]]
                );
            } catch (\Throwable $e) {
                // Ignore and continue; caller can still use partial totals.
            }
        }

        return $count;
    }

    public function create(array $data): array
    {
        try {
            if (empty($data['studentId'])) {
                return [
                    'success' => false,
                    'message' => 'Student is required.'
                ];
            }

            $student = StudentService::find((string) $data['studentId']);
            $houseId = $data['houseId'] ?? ($student['houseId'] ?? null);
            $studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : '';
            $severity = $this->normalizeSeverity($data['severity'] ?? 'normal');
            $diagnosis = $data['diagnosis'] ?? '';
            $isEmergency = in_array($severity, ['severe', 'critical', 'emergency'], true);
            $createdAt = $data['createdAt'] ?? date('c');
            $appConfig = function_exists('app_config') ? app_config() : [];

            $recordedByName = $data['recordedByName'] ?? null;
            if (!$recordedByName && !empty($data['recordedBy']) && function_exists('current_user')) {
                $u = current_user();
                if (($u['uid'] ?? '') === $data['recordedBy'] || ($u['id'] ?? '') === $data['recordedBy']) {
                    $recordedByName = trim(($u['name'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? '')));
                    if (!empty($u['role'])) {
                        $recordedByName .= ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')';
                    }
                }
            }

            $recordData = [
                'studentId' => $data['studentId'],
                'houseId' => $houseId,
                'studentName' => $studentName,
                'diagnosis' => $diagnosis,
                'treatment' => $data['treatment'] ?? '',
                'notes' => $data['notes'] ?? '',
                'severity' => $severity,
                'recordedBy' => $data['recordedBy'] ?? null,
                'recordedByName' => $recordedByName,
                'createdAt' => $createdAt,
                'followUpDate' => $data['followUpDate'] ?? ($isEmergency ? date('c', strtotime('+' . (int) ($appConfig['emergency_follow_up_hours'] ?? 24) . ' hours')) : null),
                'responseDueAt' => $data['responseDueAt'] ?? ($isEmergency ? date('c', strtotime('+' . (int) ($appConfig['emergency_response_minutes'] ?? 15) . ' minutes')) : null),
            ];

            $id = $this->firebase->addDocument($this->collection, $recordData);

            (new AuditService())->logMedicalRecordChange(
                $id,
                (string) $data['studentId'],
                'created',
                $data['recordedBy'] ?? $this->currentActor(),
                ['severity' => ['from' => '', 'to' => $severity]],
                'Medical record created'
            );

            // Automatically notify the House Master / Mistress / Senior Houseparent of the student's house
            if (!empty($houseId)) {
                try {
                    $notificationService = new NotificationService();
                    $userService = new UserService();
                    $houseUsers = $userService->byHouse((string) $houseId);
                    $seniorHouseparents = $userService->byRole(ROLE_SENIOR_HOUSEPARENT);

                    $targetUsers = [];
                    foreach (array_merge($houseUsers, $seniorHouseparents) as $user) {
                        $role = (string) ($user['role'] ?? '');
                        $house = $user['houseId'] ?? $user['house_id'] ?? null;

                        if (in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) && $house === $houseId) {
                            $targetUsers[] = $user;
                            continue;
                        }

                        if ($role === ROLE_SENIOR_HOUSEPARENT) {
                            $targetUsers[] = $user;
                        }
                    }

                    $targetUsers = array_values(array_unique($targetUsers, SORT_REGULAR));

                    foreach ($targetUsers as $user) {
                        $targetUid = $user['uid'] ?? $user['id'] ?? null;
                        if ($targetUid) {
                            $severityLabel = ucfirst($severity);
                            $notificationService->create([
                                'userId' => $targetUid,
                                'title' => "Clinic Health Report: " . ($studentName ?: 'Student'),
                                'message' => "Health record logged for " . ($studentName ?: 'Student') . " [{$severityLabel}]. Diagnosis: {$diagnosis}.",
                                'type' => in_array($severity, ['severe', 'emergency', 'critical']) ? 'danger' : 'info',
                                'link' => 'views/house-master/health-reports/index.php',
                                'from' => $data['recordedBy'] ?? null,
                                'createdAt' => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {}
            }

            return [
                'success' => true,
                'message' => 'Medical record created successfully.',
                'id' => $id
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to create medical record: ' . $e->getMessage()
            ];
        }
    }

    public function update(
        string $id,
        array $data
    ): array {
        try {
            $existing = $this->find($id) ?? [];
            if (isset($data['severity'])) {
                $data['severity'] = $this->normalizeSeverity($data['severity']);
            }

            $this->firebase->updateDocument(
                $this->collection,
                $id,
                $data
            );

            $changes = [];
            foreach ($data as $field => $value) {
                $previous = $existing[$field] ?? '';
                if ((string) $previous !== (string) $value) {
                    $changes[$field] = ['from' => $previous, 'to' => $value];
                }
            }

            if (!empty($changes)) {
                (new AuditService())->logMedicalRecordChange(
                    $id,
                    (string) ($existing['studentId'] ?? $data['studentId'] ?? ''),
                    isset($changes['severity']) ? 'severity_changed' : 'updated',
                    $data['updatedBy'] ?? $this->currentActor(),
                    $changes,
                    'Medical record updated'
                );
            }

            return [
                'success' => true,
                'message' => 'Medical record updated successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to update medical record.'
            ];
        }
    }

    public function incidents(): array
    {
        try {
            $records = [];
            $seen = [];

            foreach (
                [
                    ['severity', 'in', ['severe', 'emergency', 'critical']],
                    ['incident', '!=', ''],
                ]
                as [$field, $op, $value]
            ) {
                $batch = $this->firebase->getCollection(
                    $this->collection,
                    [[$field, $op, $value]],
                    150
                );

                foreach ($batch as $record) {
                    $recordId = (string) ($record['id'] ?? '');
                    if ($recordId !== '' && !isset($seen[$recordId])) {
                        $seen[$recordId] = true;
                        $records[] = $record;
                    }
                }
            }

            usort($records, static fn(array $first, array $second): int => strcmp(
                (string) ($second['createdAt'] ?? ''),
                (string) ($first['createdAt'] ?? '')
            ));

            return $records;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function reports(): array
    {
        $result = [
            'total' => $this->count(),
            'normal' => 0,
            'moderate' => 0,
            'severe' => 0,
            'emergency' => 0,
            'critical' => 0
        ];

        foreach (['normal', 'moderate', 'severe', 'emergency', 'critical'] as $severity) {
            try {
                $result[$severity] = $this->firebase->count(
                    $this->collection,
                    [['severity', '=', $severity]]
                );
            } catch (\Throwable $e) {
                $result[$severity] = 0;
            }
        }

        return $result;
    }

    private function normalizeSeverity(?string $severity): string
    {
        $severity = strtolower(trim((string) $severity));
        return in_array($severity, ['normal', 'moderate', 'severe', 'emergency', 'critical'], true)
            ? $severity
            : 'normal';
    }

    private function currentActor(): ?string
    {
        return \function_exists('current_user_id') ? \current_user_id() : null;
    }
}
