<?php

namespace App\Services;

class BedService
{
    public static function all(): array
    {
        return FirebaseService::getInstance()->getCollection(\COL_BEDS, [], 1000);
    }

    public static function find(string $id): ?array
    {
        return FirebaseService::getInstance()->getDocument(\COL_BEDS, $id);
    }

    public static function create(array $data): array
    {
        $bedNumber = trim((string) ($data['bedNumber'] ?? ''));
        $roomId = trim((string) ($data['roomId'] ?? ''));
        $capacity = max(1, (int) ($data['capacity'] ?? 1));
        if ($bedNumber === '' || $roomId === '') {
            return ['success' => false, 'message' => 'Bed number and room are required.'];
        }
        if (!RoomService::find($roomId)) {
            return ['success' => false, 'message' => 'Selected room was not found.'];
        }

        foreach (self::all() as $bed) {
            if ((string) ($bed['roomId'] ?? '') === $roomId && strtolower((string) ($bed['bedNumber'] ?? '')) === strtolower($bedNumber)) {
                return ['success' => false, 'message' => 'That bed number already exists in the selected room.'];
            }
        }

        $id = FirebaseService::getInstance()->addDocument(\COL_BEDS, [
            'bedNumber' => $bedNumber,
            'roomId' => $roomId,
            'capacity' => $capacity,
            'studentId' => null,
            'status' => ($data['status'] ?? 'available') === 'maintenance' ? 'maintenance' : 'available',
        ]);
        return ['success' => true, 'message' => 'Bed created successfully.', 'id' => $id];
    }

    public static function assign(string $bedId, string $studentId): array
    {
        $bed = self::find($bedId);
        $student = StudentService::find($studentId);
        if (!$bed) return ['success' => false, 'message' => 'Bed not found.'];
        if (!$student) return ['success' => false, 'message' => 'Student not found.'];
        if (($bed['status'] ?? 'available') === 'maintenance') return ['success' => false, 'message' => 'Maintenance beds cannot be assigned.'];
        if (!empty($bed['studentId']) && (string) $bed['studentId'] !== $studentId) return ['success' => false, 'message' => 'Bed is already assigned to another student.'];
        if (in_array(strtolower((string) ($student['status'] ?? 'active')), ['inactive', 'suspended'], true)) return ['success' => false, 'message' => 'Inactive or suspended students cannot be assigned a bed.'];

        $roomId = (string) ($bed['roomId'] ?? '');
        if (!empty($student['roomId']) && (string) $student['roomId'] !== $roomId) return ['success' => false, 'message' => 'Student is assigned to a different room. Transfer the student first.'];
        foreach (self::all() as $existingBed) {
            if ((string) ($existingBed['studentId'] ?? '') === $studentId && (string) ($existingBed['id'] ?? '') !== $bedId) return ['success' => false, 'message' => 'Student already has another bed.'];
        }

        FirebaseService::getInstance()->updateDocument(\COL_BEDS, $bedId, ['studentId' => $studentId, 'status' => 'occupied']);
        if (empty($student['roomId'])) {
            $room = RoomService::find($roomId);
            $occupied = (int) ($room['occupied'] ?? 0) + 1;
            RoomService::update($roomId, [
                'occupied' => $occupied,
                'status' => $occupied >= (int) ($room['capacity'] ?? 0) ? 'full' : 'occupied',
            ]);
            StudentService::update($studentId, ['roomId' => $roomId]);
        }
        return ['success' => true, 'message' => 'Bed assigned successfully.'];
    }

    public static function unassign(string $bedId): array
    {
        $bed = self::find($bedId);
        if (!$bed) return ['success' => false, 'message' => 'Bed not found.'];
        FirebaseService::getInstance()->updateDocument(\COL_BEDS, $bedId, [
            'studentId' => null,
            'status' => ($bed['status'] ?? '') === 'maintenance' ? 'maintenance' : 'available',
        ]);
        if (!empty($bed['studentId'])) {
            $student = StudentService::find((string) $bed['studentId']);
            if ($student && (string) ($student['roomId'] ?? '') === (string) ($bed['roomId'] ?? '')) {
                $room = RoomService::find((string) $bed['roomId']);
                $occupied = max(0, (int) ($room['occupied'] ?? 0) - 1);
                if ($room) RoomService::update((string) $bed['roomId'], ['occupied' => $occupied, 'status' => $occupied > 0 ? 'occupied' : 'available']);
                StudentService::update((string) $bed['studentId'], ['roomId' => null]);
            }
        }
        return ['success' => true, 'message' => 'Bed assignment removed.'];
    }

    public static function update(string $id, array $data): array
    {
        $bed = self::find($id);
        if (!$bed) return ['success' => false, 'message' => 'Bed not found.'];
        $roomId = trim((string) ($data['roomId'] ?? $bed['roomId'] ?? ''));
        $bedNumber = trim((string) ($data['bedNumber'] ?? $bed['bedNumber'] ?? ''));
        $capacity = max(1, (int) ($data['capacity'] ?? $bed['capacity'] ?? 1));
        $status = ($data['status'] ?? $bed['status'] ?? 'available') === 'maintenance' ? 'maintenance' : (($bed['studentId'] ?? null) ? 'occupied' : 'available');
        if ($bedNumber === '' || !$roomId || !RoomService::find($roomId)) return ['success' => false, 'message' => 'Bed number and a valid room are required.'];
        if ($status === 'maintenance' && !empty($bed['studentId'])) return ['success' => false, 'message' => 'Unassign the student before putting this bed under maintenance.'];
        if (!empty($bed['studentId']) && (string) ($bed['roomId'] ?? '') !== $roomId) return ['success' => false, 'message' => 'Unassign the student before moving this bed to another room.'];
        foreach (self::all() as $existingBed) {
            if ((string) ($existingBed['id'] ?? '') !== $id && (string) ($existingBed['roomId'] ?? '') === $roomId && strtolower((string) ($existingBed['bedNumber'] ?? '')) === strtolower($bedNumber)) return ['success' => false, 'message' => 'That bed number already exists in the selected room.'];
        }
        FirebaseService::getInstance()->updateDocument(\COL_BEDS, $id, ['bedNumber' => $bedNumber, 'roomId' => $roomId, 'capacity' => $capacity, 'status' => $status]);
        return ['success' => true, 'message' => 'Bed updated successfully.'];
    }

    public static function delete(string $id): array
    {
        $bed = self::find($id);
        if (!$bed) return ['success' => false, 'message' => 'Bed not found.'];
        if (!empty($bed['studentId'])) return ['success' => false, 'message' => 'Assigned beds cannot be deleted.'];
        FirebaseService::getInstance()->deleteDocument(\COL_BEDS, $id);
        return ['success' => true, 'message' => 'Bed deleted successfully.'];
    }
}
