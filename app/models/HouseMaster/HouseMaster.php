<?php

namespace App\Models;

use App\Services\FirebaseService;

/** Profile record for a house_master-role user; links a user to the house(s) they run. */
class HouseMaster
{
    public const COLLECTION = 'house_masters';

    public string $id;
    public string $userId;
    public ?string $houseId;
    public string $phone;
    public string $status;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id        = $data['id'] ?? '';
        $this->userId    = $data['userId'] ?? '';
        $this->houseId   = $data['houseId'] ?? null;
        $this->phone     = $data['phone'] ?? '';
        $this->status    = $data['status'] ?? STATUS_ACTIVE;
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

    public static function byUser(string $userId): ?self
    {
        $rows = self::all([['userId', '=', $userId]]);
        return $rows[0] ?? null;
    }

    public function save(): string
    {
        $data = ['userId' => $this->userId, 'houseId' => $this->houseId, 'phone' => $this->phone, 'status' => $this->status];
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
        return ['id' => $this->id, 'userId' => $this->userId, 'houseId' => $this->houseId,
            'phone' => $this->phone, 'status' => $this->status,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
