<?php

namespace App\Models;

use App\Services\FirebaseService;

/** Read-mostly audit trail. Rows are written via ActivityLogService::log(). */
class ActivityLog
{
    public const COLLECTION = 'activity_logs';

    public string $id;
    public string $userId;
    public string $action;
    public string $description;
    public array $meta;
    public ?string $ip;
    public ?string $createdAt;

    public function __construct(array $data)
    {
        $this->id          = $data['id'] ?? '';
        $this->userId      = $data['userId'] ?? '';
        $this->action       = $data['action'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->meta         = $data['meta'] ?? [];
        $this->ip           = $data['ip'] ?? null;
        $this->createdAt    = $data['createdAt'] ?? null;
    }

    public static function find(string $id): ?self
    {
        $data = FirebaseService::getInstance()->getDocument(self::COLLECTION, $id);
        return $data ? new self($data) : null;
    }

    /** @return self[] */
    public static function all(array $wheres = [], int $limit = 200): array
    {
        $rows = FirebaseService::getInstance()->getCollection(self::COLLECTION, $wheres, $limit);
        return array_map(fn ($r) => new self($r), $rows);
    }

    public static function forUser(string $userId, int $limit = 100): array
    {
        return self::all([['userId', '=', $userId]], $limit);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'userId' => $this->userId, 'action' => $this->action,
            'description' => $this->description, 'meta' => $this->meta, 'ip' => $this->ip,
            'createdAt' => $this->createdAt];
    }
}
