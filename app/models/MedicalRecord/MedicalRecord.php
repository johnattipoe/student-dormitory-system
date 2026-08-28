<?php

namespace App\Models;

use App\Services\FirebaseService;

class MedicalRecord
{
    public const COLLECTION = 'medical_records';

    public string $id;
    public string $studentId;
    public string $type;          // checkup | treatment | emergency | vaccination
    public string $diagnosis;
    public string $treatment;
    public string $medication;
    public bool $isEmergency;
    public ?string $recordedBy;   // nurse user id
    public ?string $followUpDate;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id            = $data['id'] ?? '';
        $this->studentId     = $data['studentId'] ?? '';
        $this->type          = $data['type'] ?? 'checkup';
        $this->diagnosis     = $data['diagnosis'] ?? '';
        $this->treatment     = $data['treatment'] ?? '';
        $this->medication    = $data['medication'] ?? '';
        $this->isEmergency   = (bool) ($data['isEmergency'] ?? false);
        $this->recordedBy    = $data['recordedBy'] ?? null;
        $this->followUpDate  = $data['followUpDate'] ?? null;
        $this->createdAt     = $data['createdAt'] ?? null;
        $this->updatedAt     = $data['updatedAt'] ?? null;
    }

    public static function find(string $id): ?self
    {
        $data = FirebaseService::getInstance()->getDocument(self::COLLECTION, $id);
        return $data ? new self($data) : null;
    }

    /** @return self[] */
    public static function all(array $wheres = []): array
    {
        $rows = FirebaseService::getInstance()->getCollection(self::COLLECTION, $wheres);
        return array_map(fn ($r) => new self($r), $rows);
    }

    public static function byStudent(string $studentId): array
    {
        return self::all([['studentId', '=', $studentId]]);
    }

    public static function emergencies(): array
    {
        return self::all([['isEmergency', '=', true]]);
    }

    public function save(): string
    {
        $data = ['studentId' => $this->studentId, 'type' => $this->type, 'diagnosis' => $this->diagnosis,
            'treatment' => $this->treatment, 'medication' => $this->medication,
            'isEmergency' => $this->isEmergency, 'recordedBy' => $this->recordedBy,
            'followUpDate' => $this->followUpDate];
        if ($this->id) {
            FirebaseService::getInstance()->updateDocument(self::COLLECTION, $this->id, $data);
            return $this->id;
        }
        $this->id = FirebaseService::getInstance()->addDocument(self::COLLECTION, $data);
        return $this->id;
    }

    public function delete(): void
    {
        FirebaseService::getInstance()->deleteDocument(self::COLLECTION, $this->id);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'studentId' => $this->studentId, 'type' => $this->type,
            'diagnosis' => $this->diagnosis, 'treatment' => $this->treatment, 'medication' => $this->medication,
            'isEmergency' => $this->isEmergency, 'recordedBy' => $this->recordedBy,
            'followUpDate' => $this->followUpDate, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
