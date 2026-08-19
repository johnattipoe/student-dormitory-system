<?php

namespace App\Services;

/**
 * CRUD for the `students` collection. Treat this file as the template
 * for RoomService, AttendanceService, VisitorService, IncidentService,
 * MedicalService, etc. — same shape, different collection + fields.
 */
class StudentService
{
    public static function all(?string $houseId = null): array
    {
        if ($houseId) {
            return FirebaseService::getInstance()->where(\COL_STUDENTS, 'houseId', '=', $houseId);
        }
        return FirebaseService::getInstance()->getCollection(\COL_STUDENTS, [], 500);
    }

    public static function find(string $id): ?array
    {
        return FirebaseService::getInstance()->getDocument(\COL_STUDENTS, $id);
    }

    public static function create(array $data): string
    {
        return FirebaseService::getInstance()->addDocument(\COL_STUDENTS, [
            'firstName'   => $data['firstName'] ?? '',
            'lastName'    => $data['lastName'] ?? '',
            'email'       => $data['email'] ?? '',
            'phone'       => $data['phone'] ?? '',
            'gender'      => $data['gender'] ?? '',
            'admissionNo' => $data['admissionNo'] ?? '',
            'course'      => $data['course'] ?? '',
            'level'       => $data['level'] ?? '',
            'houseId'     => $data['houseId'] ?? null,
            'roomId'      => $data['roomId'] ?? null,
            'guardianName'  => $data['guardianName'] ?? '',
            'guardianPhone' => $data['guardianPhone'] ?? '',
            'guardianEmail' => $data['guardianEmail'] ?? '',
            'status'      => $data['status'] ?? 'active',
        ]);
    }

    public static function update(string $id, array $data): void
    {
        FirebaseService::getInstance()->updateDocument(\COL_STUDENTS, $id, $data);
    }

    public static function delete(string $id): void
    {
        FirebaseService::getInstance()->deleteDocument(\COL_STUDENTS, $id);
    }

    public static function count(?string $houseId = null): int
    {
        return count(self::all($houseId));
    }

    public static function byHouse(?string $houseId): array
    {
        if (!$houseId) {
            return [];
        }

        return self::all($houseId);
    }

    public static function countByHouse(?string $houseId): int
    {
        return count(self::byHouse($houseId));
    }

    public static function search(array $all, string $term): array
    {
        $term = strtolower(trim($term));
        if ($term === '') return $all;
        return array_values(array_filter($all, function ($s) use ($term) {
            $haystack = strtolower(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? '') . ' ' . ($s['admissionNo'] ?? '') . ' ' . ($s['email'] ?? ''));
            return str_contains($haystack, $term);
        }));
    }

    public static function updateFlags(string $id, array $flags): void
    {
        $data = [];
        if (isset($flags['flagged'])) $data['flagged'] = $flags['flagged'];
        if (isset($flags['flagType'])) $data['flagType'] = $flags['flagType'];
        if (isset($flags['flagReason'])) $data['flagReason'] = $flags['flagReason'];
        if (isset($flags['flaggedAt'])) $data['flaggedAt'] = $flags['flaggedAt'];
        if (isset($flags['flaggedBy'])) $data['flaggedBy'] = $flags['flaggedBy'];
        if (!empty($data)) {
            self::update($id, $data);
        }
    }
}
