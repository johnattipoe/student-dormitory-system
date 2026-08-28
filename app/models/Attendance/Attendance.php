<?php

namespace App\Models;

use App\Services\FirebaseService;

class Attendance
{
    public const COLLECTION = 'attendance';

    public string $id;
    public string $studentId;
    public ?string $houseId;
    public string $date;   // Y-m-d
    public string $status; // present | absent | excused | late
    public ?string $markedBy;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id        = $data['id'] ?? '';
        $this->studentId = $data['studentId'] ?? '';
        $this->houseId   = $data['houseId'] ?? null;
        $this->date      = $data['date'] ?? '';
        $this->status    = $data['status'] ?? 'absent';
        $this->markedBy  = $data['markedBy'] ?? null;
        $this->createdAt = $data['createdAt'] ?? null;
        $this->updatedAt = $data['updatedAt'] ?? null;
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

    public static function byDate(string $date, ?string $houseId = null): array
    {
        $wheres = [['date', '=', $date]];
        if ($houseId) $wheres[] = ['houseId', '=', $houseId];
        return self::all($wheres);
    }

    public static function byStudent(string $studentId, int $limit = 90): array
    {
        $rows = FirebaseService::getInstance()->getCollection(self::COLLECTION, [['studentId', '=', $studentId]], $limit);
        return array_map(fn ($r) => new self($r), $rows);
    }

    public function save(): string
    {
        $data = ['studentId' => $this->studentId, 'houseId' => $this->houseId, 'date' => $this->date,
            'status' => $this->status, 'markedBy' => $this->markedBy];
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
            'date' => $this->date, 'status' => $this->status, 'markedBy' => $this->markedBy,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
