<?php

namespace App\Models;

use App\Services\FirebaseService;

/** A student's advance request for a visitor to be allowed onto the premises. */
class VisitorRequest
{
    public const COLLECTION = 'visitor_requests';

    public string $id;
    public string $studentId;
    public ?string $houseId;
    public string $visitorName;
    public string $visitorPhone;
    public string $purpose;
    public string $requestedDate;
    public string $status; // pending | approved | rejected | fulfilled
    public ?string $approvedBy;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id            = $data['id'] ?? '';
        $this->studentId     = $data['studentId'] ?? '';
        $this->houseId       = $data['houseId'] ?? null;
        $this->visitorName   = $data['visitorName'] ?? '';
        $this->visitorPhone  = $data['visitorPhone'] ?? '';
        $this->purpose       = $data['purpose'] ?? '';
        $this->requestedDate = $data['requestedDate'] ?? '';
        $this->status        = $data['status'] ?? 'pending';
        $this->approvedBy    = $data['approvedBy'] ?? null;
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

    public static function pending(?string $houseId = null): array
    {
        $wheres = [['status', '=', 'pending']];
        if ($houseId) $wheres[] = ['houseId', '=', $houseId];
        return self::all($wheres);
    }

    public function save(): string
    {
        $data = ['studentId' => $this->studentId, 'houseId' => $this->houseId,
            'visitorName' => $this->visitorName, 'visitorPhone' => $this->visitorPhone,
            'purpose' => $this->purpose, 'requestedDate' => $this->requestedDate,
            'status' => $this->status, 'approvedBy' => $this->approvedBy];
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
            'visitorName' => $this->visitorName, 'visitorPhone' => $this->visitorPhone,
            'purpose' => $this->purpose, 'requestedDate' => $this->requestedDate,
            'status' => $this->status, 'approvedBy' => $this->approvedBy,
            'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
