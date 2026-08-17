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
            return $this->firebase->getCollection(
                $this->collection
            );
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

            if (
                $severity === 'emergency' ||
                $severity === 'critical'
            ) {
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

            $id = $this->firebase->addDocument(
                $this->collection,
                [
                    'studentId' => $data['studentId'],
                    'diagnosis' => $data['diagnosis'] ?? '',
                    'treatment' => $data['treatment'] ?? '',
                    'notes' => $data['notes'] ?? '',
                    'severity' => $data['severity'] ?? 'normal',
                    'recordedBy' => $data['recordedBy'] ?? null,
                    'createdAt' => $data['createdAt'] ?? date('c')
                ]
            );

            return [
                'success' => true,
                'message' => 'Medical record created successfully.',
                'id' => $id
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to create medical record.'
            ];
        }
    }

    public function update(
        string $id,
        array $data
    ): array {
        try {

            $this->firebase->updateDocument(
                $this->collection,
                $id,
                $data
            );

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
                    return !empty($record['incident']);
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
}
