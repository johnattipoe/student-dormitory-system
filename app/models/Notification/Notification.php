<?php

namespace App\Models;

use App\Services\FirebaseService;

class Notification
{
    public const COLLECTION = 'notifications';

    public string $id;
    public ?string $userId;   // specific recipient, or null if role/house broadcast
    public ?string $role;     // broadcast target role, or null
    public ?string $houseId;  // broadcast target house, or null
    public string $title;
    public string $message;
    public string $type;      // info | warning | alert
    public bool $isRead;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id        = $data['id'] ?? '';
        $this->userId    = $data['userId'] ?? null;
        $this->role      = $data['role'] ?? null;
        $this->houseId   = $data['houseId'] ?? null;
        $this->title     = $data['title'] ?? '';
        $this->message   = $data['message'] ?? '';
        $this->type      = $data['type'] ?? 'info';
        $this->isRead    = (bool) ($data['isRead'] ?? false);
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

    public static function forUser(string $userId): array
    {
        return self::all([['userId', '=', $userId]]);
    }

    public static function unreadForUser(string $userId): array
    {
        return self::all([['userId', '=', $userId], ['isRead', '=', false]]);
    }

    public function markRead(): void
    {
        $this->isRead = true;
        $this->save();
    }

    public function save(): string
    {
        $data = ['userId' => $this->userId, 'role' => $this->role, 'houseId' => $this->houseId,
            'title' => $this->title, 'message' => $this->message, 'type' => $this->type, 'isRead' => $this->isRead];
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
        return ['id' => $this->id, 'userId' => $this->userId, 'role' => $this->role, 'houseId' => $this->houseId,
            'title' => $this->title, 'message' => $this->message, 'type' => $this->type, 'isRead' => $this->isRead,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
