<?php

namespace App\Models;

use App\Services\FirebaseService;

/**
 * User model - thin wrapper over the `users` Firestore collection.
 * Other models (Student, HouseMaster, etc.) follow this same pattern:
 * a collection name + typed getters/setters + static finder helpers.
 */
class User
{
    public const COLLECTION = 'users';

    public string $id;
    public string $name;
    public string $email;
    public string $role;
    public ?string $houseId;
    public string $status;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id        = $data['id'] ?? '';
        $this->name      = $data['name'] ?? '';
        $this->email     = $data['email'] ?? '';
        $this->role      = $data['role'] ?? '';
        $this->houseId   = $data['houseId'] ?? null;
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

    /** @return self[] */
    public static function byRole(string $role): array
    {
        return self::all([['role', '=', $role]]);
    }

    public function save(): string
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'houseId' => $this->houseId,
            'status' => $this->status,
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
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'role' => $this->role, 'houseId' => $this->houseId, 'status' => $this->status,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt,
        ];
    }
}
