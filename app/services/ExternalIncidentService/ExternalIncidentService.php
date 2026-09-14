<?php

namespace App\Services;

class ExternalIncidentService
{
    private FirebaseService $firebase;

    private string $collection = 'external_incidents';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            return $this->firebase->getCollection($this->collection, [], 500);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function allByHouse(?string $houseId): array
    {
        if (!$houseId) {
            return [];
        }

        try {
            $records = $this->firebase->getCollection($this->collection, [['houseId', '=', $houseId]], 500);
            usort($records, function ($a, $b) {
                $aTime = (string) ($a['uploadedAt'] ?? $a['createdAt'] ?? '');
                $bTime = (string) ($b['uploadedAt'] ?? $b['createdAt'] ?? '');
                return strcmp($bTime, $aTime);
            });
            return $records;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function findByFileName(string $fileName): ?array
    {
        if ($fileName === '') {
            return null;
        }

        foreach ($this->all() as $record) {
            if ((string) ($record['fileName'] ?? '') === $fileName) {
                return $record;
            }
        }

        return null;
    }

    public function create(array $data): string
    {
        return $this->firebase->addDocument($this->collection, $data);
    }

    public function update(string $id, array $data): void
    {
        $this->firebase->updateDocument($this->collection, $id, $data);
    }

    public function delete(string $id): void
    {
        $this->firebase->deleteDocument($this->collection, $id);
    }
}
