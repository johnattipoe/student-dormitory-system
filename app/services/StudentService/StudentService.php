<?php

namespace App\Services;

/**
 * CRUD for the `students` collection. Treat this file as the template
 * for RoomService, AttendanceService, VisitorService, IncidentService,
 * MedicalService, etc. — same shape, different collection + fields.
 */
class StudentService
{
    public static function all(?string $houseId = null, int $limit = 150): array
    {
        if ($houseId) {
            return FirebaseService::getInstance()->where(\COL_STUDENTS, 'houseId', '=', $houseId, $limit);
        }
        return FirebaseService::getInstance()->getCollection(\COL_STUDENTS, [], $limit);
    }

    public static function page(string $houseId, int $limit, int $page): array
    {
        $houseId = trim($houseId);
        if ($houseId === '') {
            return [];
        }

        $limit = max(1, min(150, $limit));
        $page = max(1, $page);

        return FirebaseService::getInstance()->getCollection(
            \COL_STUDENTS,
            [['houseId', '=', $houseId]],
            $limit,
            ($page - 1) * $limit,
            false
        );
    }

    public static function allRecords(?string $houseId = null): array
    {
        $wheres = $houseId ? [['houseId', '=', $houseId]] : [];
        return FirebaseService::getInstance()->getAllCollection(\COL_STUDENTS, $wheres);
    }

    public static function find(string $id): ?array
    {
        return FirebaseService::getInstance()->getDocument(\COL_STUDENTS, $id);
    }

    public static function findByIdentifier(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $student = self::find($id);
        if ($student !== null) {
            return $student;
        }

        foreach (['studentId', 'admissionNo', 'userId', 'uid', 'email'] as $field) {
            $matches = FirebaseService::getInstance()->getCollection(
                \COL_STUDENTS,
                [[$field, '=', $id]],
                1,
                0,
                false
            );
            if (!empty($matches[0])) {
                return $matches[0];
            }
        }

        return null;
    }

    public static function findForUser(array $user): ?array
    {
        $checkedIds = [];
        foreach ([$user['studentId'] ?? null, $user['uid'] ?? null, $user['id'] ?? null] as $candidateId) {
            $candidateId = trim((string) $candidateId);
            if ($candidateId === '' || isset($checkedIds[$candidateId])) {
                continue;
            }

            $checkedIds[$candidateId] = true;
            $student = self::find($candidateId);
            if ($student !== null) {
                return $student;
            }
        }

        $email = trim((string) ($user['email'] ?? ''));
        return $email !== '' ? self::findByEmail($email) : null;
    }

    public static function forRoom(string $roomId, int $limit = 150, ?string $houseId = null): array
    {
        $roomId = trim($roomId);
        if ($roomId === '') {
            return [];
        }

        $wheres = [['roomId', '=', $roomId]];
        $houseId = trim((string) $houseId);
        if ($houseId !== '') {
            $wheres[] = ['houseId', '=', $houseId];
        }

        return FirebaseService::getInstance()->getCollection(
            \COL_STUDENTS,
            $wheres,
            max(1, min(150, $limit)),
            0,
            false
        );
    }

    public static function forIds(array $studentIds, int $limit = 150): array
    {
        $limit = max(1, min(150, $limit));
        $studentIds = array_values(array_unique(array_filter(array_map(
            static fn($studentId): string => trim((string) $studentId),
            $studentIds
        ))));
        $studentsById = [];

        foreach (array_chunk($studentIds, 30) as $studentIdBatch) {
            $remainingLimit = $limit - count($studentsById);
            if ($remainingLimit <= 0) {
                break;
            }

            foreach (FirebaseService::getInstance()->getCollection(
                \COL_STUDENTS,
                [['__name__', 'in', $studentIdBatch]],
                $remainingLimit,
                0,
                false
            ) as $student) {
                $id = (string) ($student['id'] ?? '');
                if ($id !== '') {
                    $studentsById[$id] = $student;
                }
            }
        }

        return array_values($studentsById);
    }

    public static function create(array $data): string
    {
        return FirebaseService::getInstance()->addDocument(\COL_STUDENTS, [
            'firstName'   => $data['firstName'] ?? '',
            'lastName'    => $data['lastName'] ?? '',
            'email'       => $data['email'] ?? '',
            'phone'       => $data['phone'] ?? '',
            'nhisNumber'  => $data['nhisNumber'] ?? '',
            'gender'      => $data['gender'] ?? '',
            'admissionNo' => $data['admissionNo'] ?? '',
            'class'       => $data['class'] ?? '',
            'form'        => $data['form'] ?? $data['level'] ?? '',
            'level'       => $data['form'] ?? $data['level'] ?? '',
            'course'      => $data['course'] ?? '',
            'houseId'     => $data['houseId'] ?? null,
            'roomId'      => $data['roomId'] ?? null,
                'residenceType' => in_array(strtolower((string) ($data['residenceType'] ?? 'boarding')), ['boarding', 'day'], true)
                    ? strtolower((string) $data['residenceType'])
                    : 'boarding',
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

    public static function count(?string $houseId = null, array $additionalWheres = []): int
    {
        $wheres = [];
        if ($houseId) {
            $wheres[] = ['houseId', '=', $houseId];
        }

        return FirebaseService::getInstance()->count(\COL_STUDENTS, array_merge($wheres, $additionalWheres));
    }

    public static function stats(?string $houseId = null): array
    {
        $wheres = [];
        if ($houseId) {
            $wheres[] = ['houseId', '=', $houseId];
        }

        $result = [
            'total' => self::count($houseId),
            'active' => FirebaseService::getInstance()->count(\COL_STUDENTS, array_merge($wheres, [['status', '=', 'active']])),
            'inactive' => FirebaseService::getInstance()->count(\COL_STUDENTS, array_merge($wheres, [['status', '=', 'inactive']])),
            'suspended' => FirebaseService::getInstance()->count(\COL_STUDENTS, array_merge($wheres, [['status', '=', 'suspended']])),
            'assignedRooms' => self::count($houseId, [['roomId', '!=', '']]),
        ];

        return $result;
    }

    public static function byHouse(?string $houseId): array
    {
        if (!$houseId) {
            return [];
        }

        return self::all($houseId);
    }

    public static function findByEmail(string $email): ?array
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        $records = FirebaseService::getInstance()->getCollection(
            \COL_STUDENTS,
            [['email', '=', $email]],
            1,
            0,
            false
        );

        foreach ($records as $student) {
            if (strtolower(trim((string) ($student['email'] ?? ''))) === strtolower($email)) {
                return $student;
            }
        }

        return null;
    }

    public static function countByHouse(?string $houseId): int
    {
        return self::count($houseId);
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
