<?php

namespace App\Services;

class RoomService
{
    public static function all(?string $houseId = null): array
    {
        if ($houseId) {
            return FirebaseService::getInstance()->where(\COL_ROOMS, 'houseId', '=', $houseId);
        }
        return FirebaseService::getInstance()->getCollection(\COL_ROOMS, [], 500);
    }

    public static function find(string $id): ?array
    {
        return FirebaseService::getInstance()->getDocument(\COL_ROOMS, $id);
    }

    public static function create(array $data): string
    {
        return FirebaseService::getInstance()->addDocument(\COL_ROOMS, [
            'roomNumber' => $data['roomNumber'] ?? '',
            'houseId'    => $data['houseId'] ?? null,
            'capacity'   => (int) ($data['capacity'] ?? 1),
            'occupied'   => 0,
            'type'       => $data['type'] ?? 'standard',
            'status'     => $data['status'] ?? 'available',
        ]);
    }

    public static function update(string $id, array $data): void
    {
        FirebaseService::getInstance()->updateDocument(\COL_ROOMS, $id, $data);
    }

    public static function delete(string $id): void
    {
        $db = FirebaseService::getInstance();

        // 1. Unassign any students currently in this room
        try {
            $students = $db->where(\COL_STUDENTS, 'roomId', '=', $id);
            foreach ($students as $student) {
                if (!empty($student['id'])) {
                    $db->updateDocument(\COL_STUDENTS, $student['id'], ['roomId' => null]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if collection not available
        }

        // 2. End active allocations for this room
        try {
            $allocations = $db->where(\COL_ROOM_ALLOCATIONS, 'roomId', '=', $id);
            foreach ($allocations as $allocation) {
                if (!empty($allocation['id']) && ($allocation['status'] ?? '') === 'active') {
                    $db->updateDocument(\COL_ROOM_ALLOCATIONS, $allocation['id'], ['status' => 'ended']);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if collection not available
        }

        // 3. Delete or unassign beds in this room
        try {
            $beds = $db->where(\COL_BEDS, 'roomId', '=', $id);
            foreach ($beds as $bed) {
                if (!empty($bed['id'])) {
                    $db->deleteDocument(\COL_BEDS, $bed['id']);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if collection not available
        }

        // 4. Delete the room document
        $db->deleteDocument(\COL_ROOMS, $id);
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

    public static function available(): array
    {
        $rooms = self::all();
        return array_values(array_filter($rooms, function ($room) {
            return ((int) ($room['occupied'] ?? 0)) < ((int) ($room['capacity'] ?? 0));
        }));
    }

    /** Assign a student to a room: creates an allocation record, bumps room.occupied, sets student.roomId. */
    public static function allocate(string $roomId, string $studentId): array
    {
        $room = self::find($roomId);
        if (!$room) return ['ok' => false, 'message' => 'Room not found.'];
        if (($room['occupied'] ?? 0) >= ($room['capacity'] ?? 0)) {
            return ['ok' => false, 'message' => 'Room is already at full capacity.'];
        }

        FirebaseService::getInstance()->addDocument(\COL_ROOM_ALLOCATIONS, [
            'roomId'    => $roomId,
            'studentId' => $studentId,
            'houseId'   => $room['houseId'] ?? null,
            'status'    => 'active',
        ]);

        self::update($roomId, ['occupied' => ($room['occupied'] ?? 0) + 1]);
        StudentService::update($studentId, ['roomId' => $roomId, 'houseId' => $room['houseId'] ?? null]);

        return ['ok' => true, 'message' => 'Student allocated to room.'];
    }

    /** Remove a student from their current room. */
    public static function deallocate(string $roomId, string $studentId): array
    {
        $room = self::find($roomId);
        if (!$room) return ['ok' => false, 'message' => 'Room not found.'];

        $allocations = FirebaseService::getInstance()->where(\COL_ROOM_ALLOCATIONS, 'studentId', '=', $studentId);
        foreach ($allocations as $a) {
            if (($a['roomId'] ?? null) === $roomId && ($a['status'] ?? '') === 'active') {
                FirebaseService::getInstance()->updateDocument(\COL_ROOM_ALLOCATIONS, $a['id'], ['status' => 'ended']);
            }
        }

        self::update($roomId, ['occupied' => max(0, ($room['occupied'] ?? 1) - 1)]);
        StudentService::update($studentId, ['roomId' => null]);

        return ['ok' => true, 'message' => 'Student removed from room.'];
    }

    public static function occupancyStats(?string $houseId = null): array
    {
        $rooms = self::all($houseId);
        $totalCapacity = array_sum(array_column($rooms, 'capacity'));
        $totalOccupied = array_sum(array_column($rooms, 'occupied'));
        return [
            'rooms'       => count($rooms),
            'capacity'    => $totalCapacity,
            'occupied'    => $totalOccupied,
            'vacant'      => max(0, $totalCapacity - $totalOccupied),
            'occupancyRate' => $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 1) : 0,
        ];
    }

    public static function occupancy(?string $houseId = null): array
    {
        return self::occupancyStats($houseId);
    }

    public static function occupancyRate(?string $houseId = null): float
    {
        $stats = self::occupancyStats($houseId);
        return (float) ($stats['occupancyRate'] ?? 0);
    }
}
