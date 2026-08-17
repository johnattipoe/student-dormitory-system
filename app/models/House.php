<?php

namespace App\Models;

use App\Services\FirebaseService;

class House
{
    public const COLLECTION = 'houses';

    public string $id;
    public string $name;
    public string $gender;
    public int $capacity;
    public ?string $houseMasterId;
    public ?string $houseMistressId;
    public string $location;
    public string $status;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id               = $data['id'] ?? '';
        $this->name             = $data['name'] ?? '';
        $this->gender           = $data['gender'] ?? '';
        $this->capacity         = (int) ($data['capacity'] ?? 0);
        $this->houseMasterId    = $data['houseMasterId'] ?? null;
        $this->houseMistressId  = $data['houseMistressId'] ?? null;
        $this->location         = $data['location'] ?? '';
        $this->status           = $data['status'] ?? STATUS_ACTIVE;
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

    public static function byHouseMaster(string $houseMasterId): ?self
    {
        $rows = self::all([['houseMasterId', '=', $houseMasterId]]);
        return $rows[0] ?? null;
    }

    public function save(): string
    {
        $data = [
            'name' => $this->name, 'gender' => $this->gender, 'capacity' => $this->capacity,
            'houseMasterId' => $this->houseMasterId,
            'houseMistressId' => $this->houseMistressId,
            'location' => $this->location, 'status' => $this->status,
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
            'id' => $this->id, 'name' => $this->name, 'gender' => $this->gender,
            'capacity' => $this->capacity, 'houseMasterId' => $this->houseMasterId,
            'houseMistressId' => $this->houseMistressId,
            'location' => $this->location, 'status' => $this->status,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt,
        ];
    }
}
