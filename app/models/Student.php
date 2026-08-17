<?php

namespace App\Models;

use App\Services\FirebaseService;

class Student
{
    public const COLLECTION = 'students';

    public string $id;
    public string $firstName;
    public string $lastName;
    public string $email;
    public string $phone;
    public string $gender;
    public string $admissionNo;
    public string $course;
    public string $level;
    public ?string $houseId;
    public ?string $roomId;
    public string $guardianName;
    public string $guardianPhone;
    public string $status;
    public ?string $createdAt;
    public ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id            = $data['id'] ?? '';
        $this->firstName     = $data['firstName'] ?? '';
        $this->lastName      = $data['lastName'] ?? '';
        $this->email         = $data['email'] ?? '';
        $this->phone         = $data['phone'] ?? '';
        $this->gender        = $data['gender'] ?? '';
        $this->admissionNo   = $data['admissionNo'] ?? '';
        $this->course        = $data['course'] ?? '';
        $this->level         = $data['level'] ?? '';
        $this->houseId       = $data['houseId'] ?? null;
        $this->roomId        = $data['roomId'] ?? null;
        $this->guardianName  = $data['guardianName'] ?? '';
        $this->guardianPhone = $data['guardianPhone'] ?? '';
        $this->status        = $data['status'] ?? STATUS_ACTIVE;
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

    /** @return self[] */
    public static function byHouse(string $houseId): array
    {
        return self::all([['houseId', '=', $houseId]]);
    }

    /** @return self[] */
    public static function byRoom(string $roomId): array
    {
        return self::all([['roomId', '=', $roomId]]);
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function save(): string
    {
        $data = [
            'firstName' => $this->firstName, 'lastName' => $this->lastName,
            'email' => $this->email, 'phone' => $this->phone, 'gender' => $this->gender,
            'admissionNo' => $this->admissionNo, 'course' => $this->course, 'level' => $this->level,
            'houseId' => $this->houseId, 'roomId' => $this->roomId,
            'guardianName' => $this->guardianName, 'guardianPhone' => $this->guardianPhone,
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
            'id' => $this->id, 'firstName' => $this->firstName, 'lastName' => $this->lastName,
            'email' => $this->email, 'phone' => $this->phone, 'gender' => $this->gender,
            'admissionNo' => $this->admissionNo, 'course' => $this->course, 'level' => $this->level,
            'houseId' => $this->houseId, 'roomId' => $this->roomId,
            'guardianName' => $this->guardianName, 'guardianPhone' => $this->guardianPhone,
            'status' => $this->status, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt,
        ];
    }
}
