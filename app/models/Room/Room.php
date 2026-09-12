<?php

namespace App\Models;

use App\Services\FirebaseService;

class Room
{
    public const COLLECTION = 'rooms';

    public string $id;
    public string $roomNumber;
    public ?string $houseId;
    public int $capacity;
    public int $occupied;
    public string $type;
    public string $status;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id         = $data['id'] ?? '';
        $this->roomNumber = $data['roomNumber'] ?? '';
        $this->houseId    = $data['houseId'] ?? null;
        $this->capacity   = (int) ($data['capacity'] ?? 1);
        $this->occupied   = (int) ($data['occupied'] ?? 0);
        $this->type       = $data['type'] ?? 'standard';
        $this->status     = $data['status'] ?? 'available';
        $this->createdAt  = $data['createdAt'] ?? null;
        $this->updatedAt  = $data['updatedAt'] ?? null;
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

    /** @return self[] */
    public static function byHouse(string $houseId): array
    {
        return self::all([['houseId', '=', $houseId]]);
    }

    public function isFull(): bool
    {
        return $this->occupied >= $this->capacity;
    }

    public function vacancies(): int
    {
        return max(0, $this->capacity - $this->occupied);
    }

    public function save(): string
    {
        $data = [
            'roomNumber' => $this->roomNumber, 'houseId' => $this->houseId,
            'capacity' => $this->capacity, 'occupied' => $this->occupied,
            'type' => $this->type, 'status' => $this->status,
        ];

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
        return [
            'id' => $this->id, 'roomNumber' => $this->roomNumber, 'houseId' => $this->houseId,
            'capacity' => $this->capacity, 'occupied' => $this->occupied, 'type' => $this->type,
            'status' => $this->status, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt,
        ];
    }
}
