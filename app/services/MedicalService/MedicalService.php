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

    public function all(): array
    {
        try {
            $records = $this->firebase->getCollection(
                $this->collection
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
        return count($this->all());
    }

    public function todayCases(): int
    {
        $records = $this->all();
        $today = date('Y-m-d');

        $count = 0;

        foreach ($records as $record) {

            $created = $record['createdAt'] ?? '';

            if ($created && str_starts_with($created, $today)) {
                $count++;
            }
        }

        return $count;
    }

    public function emergencyCases(): int
    {
        $records = $this->all();

        $count = 0;

        foreach ($records as $record) {

            $severity = strtolower(
                $record['severity'] ?? ''
            );

            if (in_array($severity, ['severe', 'emergency', 'critical'], true)) {
                $count++;
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
                'createdAt' => $data['createdAt'] ?? date('c')
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
                    $users = (new UserService())->all();
                    foreach ($users as $u) {
                        $uRole = $u['role'] ?? '';
                        $uHouse = $u['houseId'] ?? $u['house_id'] ?? null;
                        if ((in_array($uRole, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS], true) && $uHouse === $houseId) || $uRole === ROLE_SENIOR_HOUSEPARENT) {
                            $targetUid = $u['uid'] ?? $u['id'] ?? null;
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
        return array_values(
            array_filter(
                $this->all(),
                function ($record) {
                    $severity = strtolower((string) ($record['severity'] ?? ''));
                    return !empty($record['incident']) || in_array($severity, ['severe', 'emergency', 'critical'], true);
                }
            )
        );
    }

    public function reports(): array
    {
        $records = $this->all();

        $result = [
            'total' => count($records),
            'normal' => 0,
            'moderate' => 0,
            'severe' => 0,
            'emergency' => 0,
            'critical' => 0
        ];

        foreach ($records as $record) {

            $severity = strtolower(
                $record['severity'] ?? 'normal'
            );

            if (isset($result[$severity])) {
                $result[$severity]++;
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
