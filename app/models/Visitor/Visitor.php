<?php

namespace App\Models;

use App\Services\FirebaseService;

class Visitor
{
    public const COLLECTION = 'visitors';

    public string $id;
    public string $name;
    public string $phone;
    public string $idNumber;
    public string $studentId;    // student being visited
    public ?string $houseId;
    public string $purpose;
    public string $status;       // registered | checked_in | checked_out
    public ?string $checkInAt;
    public ?string $checkOutAt;
    public ?string $registeredBy;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id            = $data['id'] ?? '';
        $this->name          = $data['name'] ?? '';
        $this->phone         = $data['phone'] ?? '';
        $this->idNumber      = $data['idNumber'] ?? '';
        $this->studentId     = $data['studentId'] ?? '';
        $this->houseId       = $data['houseId'] ?? null;
        $this->purpose       = $data['purpose'] ?? '';
        $this->status        = $data['status'] ?? 'registered';
        $this->checkInAt     = $data['checkInAt'] ?? null;
        $this->checkOutAt    = $data['checkOutAt'] ?? null;
        $this->registeredBy  = $data['registeredBy'] ?? null;
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

    public static function currentlyOnPremises(): array
    {
        return self::all([['status', '=', 'checked_in']]);
    }

    public function save(): string
    {
        $data = ['name' => $this->name, 'phone' => $this->phone, 'idNumber' => $this->idNumber,
            'studentId' => $this->studentId, 'houseId' => $this->houseId, 'purpose' => $this->purpose,
            'status' => $this->status, 'checkInAt' => $this->checkInAt, 'checkOutAt' => $this->checkOutAt,
            'registeredBy' => $this->registeredBy];
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
        return ['id' => $this->id, 'name' => $this->name, 'phone' => $this->phone, 'idNumber' => $this->idNumber,
            'studentId' => $this->studentId, 'houseId' => $this->houseId, 'purpose' => $this->purpose,
            'status' => $this->status, 'checkInAt' => $this->checkInAt, 'checkOutAt' => $this->checkOutAt,
            'registeredBy' => $this->registeredBy, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
