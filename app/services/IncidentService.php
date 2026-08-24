<?php

namespace App\Services;

class IncidentService
{
    private FirebaseService $firebase;

    private string $collection = 'incidents';

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

    public function create(array $data): array
    {
        try {

            if (empty($data['title'])) {
                return [
                    'success' => false,
                    'message' => 'Incident title is required.'
                ];
            }

            $id = $this->firebase->addDocument(
                $this->collection,
                [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'type' => $data['type'] ?? 'other',
                    'studentId' => $data['studentId'] ?? null,
                    'priority' => $data['priority'] ?? 'medium',
                    'status' => 'open',
                    'reportedBy' => $data['reportedBy'] ?? null,
                    'reportedAt' => $data['reportedAt'] ?? date('c')
                ]
            );

            return [
                'success' => true,
                'message' => 'Incident reported successfully.',
                'id' => $id
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to report incident.'
            ];
        }
    }

    public function update(string $id, array $data): array
    {
        try {

            $allowedFields = ['title', 'description', 'type', 'priority', 'status', 'resolvedAt'];
            $updates = array_intersect_key($data, array_flip($allowedFields));
            if (!$updates) {
                return ['success' => false, 'message' => 'No incident changes supplied.'];
            }
            $this->firebase->updateDocument($this->collection, $id, $updates);

            return [
                'success' => true,
                'message' => 'Incident updated successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to update incident.'
            ];
        }
    }

    public function deleteForStudent(string $id, ?string $studentId): array
    {
        if (!$studentId || !$this->findOwnedIncident($id, $studentId)) {
            return [
                'success' => false,
                'message' => 'Incident not found.'
            ];
        }

        try {
            $this->firebase->deleteDocument($this->collection, $id);
            return [
                'success' => true,
                'message' => 'Incident deleted successfully.'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to delete incident.'
            ];
        }
    }

    private function findOwnedIncident(string $id, string $studentId): ?array
    {
        foreach ($this->studentIncidents($studentId) as $incident) {
            if ((string) ($incident['id'] ?? '') === $id) {
                return $incident;
            }
        }
        return null;
    }

    public function resolve(string $id): array
    {
        return $this->update(
            $id,
            [
                'status' => 'resolved',
                'resolvedAt' => date('c')
            ]
        );
    }

    public function openCount(): int
    {
        return count(
            $this->firebase->getCollection(
                $this->collection,
                [
                    ['status', '=', 'open']
                ]
            )
        );
    }

    public function openByHouse(?string $houseId): int
    {
        return count($this->byHouse($houseId, true));
    }

    public function byHouse(
        ?string $houseId,
        bool $openOnly = false
    ): array {
        if (!$houseId) {
            return [];
        }

        try {

            $students = $this->firebase->getCollection(
                'students',
                [
                    ['houseId', '=', $houseId]
                ]
            );

            $result = [];

            foreach ($students as $student) {

                $studentId = $student['studentId']
                    ?? $student['id']
                    ?? null;

                if (!$studentId) {
                    continue;
                }

                $incidents = $this->firebase->getCollection(
                    $this->collection,
                    [
                        ['studentId', '=', $studentId]
                    ]
                );

                foreach ($incidents as $incident) {

                    if (
                        $openOnly &&
                        ($incident['status'] ?? '') !== 'open'
                    ) {
                        continue;
                    }

                    $result[] = $incident;
                }
            }

            return $result;

        } catch (\Throwable $e) {
            return [];
        }
    }

    public function studentIncidents(?string $studentId): array
    {
        if (!$studentId) {
            return [];
        }

        try {
            return $this->firebase->getCollection(
                $this->collection,
                [
                    ['studentId', '=', $studentId]
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function history(): array
    {
        return $this->all();
    }

    public static function byStudent(?string $studentId): array
    {
        if (!$studentId) {
            return [];
        }
        try {
            $service = new self();
            return $service->studentIncidents($studentId);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
