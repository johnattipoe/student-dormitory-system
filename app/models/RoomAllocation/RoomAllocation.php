<?php

namespace App\Models;

use App\Services\FirebaseService;

class RoomAllocation
{
    public const COLLECTION = 'room_allocations';

    public string $id;
    public string $roomId;
    public string $studentId;
    public ?string $houseId;
    public string $status; // active | ended
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id        = $data['id'] ?? '';
        $this->roomId    = $data['roomId'] ?? '';
        $this->studentId = $data['studentId'] ?? '';
        $this->houseId   = $data['houseId'] ?? null;
        $this->status    = $data['status'] ?? 'active';
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

    public static function byStudent(string $studentId): array
    {
        return self::all([['studentId', '=', $studentId]]);
    }

    public static function byRoom(string $roomId): array
    {
        return self::all([['roomId', '=', $roomId]]);
    }

    public static function activeForStudent(string $studentId): ?self
    {
        $rows = self::all([['studentId', '=', $studentId], ['status', '=', 'active']]);
        return $rows[0] ?? null;
    }

    public function save(): string
    {
        $data = ['roomId' => $this->roomId, 'studentId' => $this->studentId, 'houseId' => $this->houseId, 'status' => $this->status];
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
        return ['id' => $this->id, 'roomId' => $this->roomId, 'studentId' => $this->studentId,
            'houseId' => $this->houseId, 'status' => $this->status,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
