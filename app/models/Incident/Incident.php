<?php

namespace App\Models;

use App\Services\FirebaseService;

class Incident
{
    public const COLLECTION = 'incidents';

    public string $id;
    public ?string $studentId;
    public ?string $houseId;
    public string $type;        // security | medical | disciplinary | other
    public string $title;
    public string $description;
    public string $severity;    // low | medium | high | critical
    public string $status;      // open | investigating | resolved | closed
    public ?string $reportedBy;
    public ?string $resolvedBy;
    public ?string $resolutionNotes;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id               = $data['id'] ?? '';
        $this->studentId        = $data['studentId'] ?? null;
        $this->houseId          = $data['houseId'] ?? null;
        $this->type             = $data['type'] ?? 'other';
        $this->title            = $data['title'] ?? '';
        $this->description      = $data['description'] ?? '';
        $this->severity         = $data['severity'] ?? 'low';
        $this->status           = $data['status'] ?? 'open';
        $this->reportedBy       = $data['reportedBy'] ?? null;
        $this->resolvedBy       = $data['resolvedBy'] ?? null;
        $this->resolutionNotes  = $data['resolutionNotes'] ?? null;
        $this->createdAt        = $data['createdAt'] ?? null;
        $this->updatedAt        = $data['updatedAt'] ?? null;
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

    public static function byHouse(string $houseId): array
    {
        return self::all([['houseId', '=', $houseId]]);
    }

    public static function byStudent(string $studentId): array
    {
        return self::all([['studentId', '=', $studentId]]);
    }

    public static function open(?string $houseId = null): array
    {
        $wheres = [['status', '=', 'open']];
        if ($houseId) $wheres[] = ['houseId', '=', $houseId];
        return self::all($wheres);
    }

    public function save(): string
    {
        $data = ['studentId' => $this->studentId, 'houseId' => $this->houseId, 'type' => $this->type,
            'title' => $this->title, 'description' => $this->description, 'severity' => $this->severity,
            'status' => $this->status, 'reportedBy' => $this->reportedBy, 'resolvedBy' => $this->resolvedBy,
            'resolutionNotes' => $this->resolutionNotes];
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
        return ['id' => $this->id, 'studentId' => $this->studentId, 'houseId' => $this->houseId,
            'type' => $this->type, 'title' => $this->title, 'description' => $this->description,
            'severity' => $this->severity, 'status' => $this->status, 'reportedBy' => $this->reportedBy,
            'resolvedBy' => $this->resolvedBy, 'resolutionNotes' => $this->resolutionNotes,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
