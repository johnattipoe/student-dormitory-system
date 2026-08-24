<?php

namespace App\Services;

class RoomAllocationService
{
    private FirebaseService $firebase;

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function allocate(array $data): array
    {
        try {

            $studentId = $data['studentId'] ?? '';
            $roomId = $data['roomId'] ?? '';

            if (!$studentId || !$roomId) {
                return [
                    'success' => false,
                    'message' => 'Student and room are required.'
                ];
            }

            $studentService = new StudentService();
            $roomService = new RoomService();

            $student = $studentService->find($studentId);
            $room = $roomService->find($roomId);

            if (!$student) {
                return [
                    'success' => false,
                    'message' => 'Student not found.'
                ];
            }

            if (in_array(strtolower((string) ($student['status'] ?? 'active')), ['inactive', 'suspended'], true)) {
                return [
                    'success' => false,
                    'message' => 'Inactive or suspended students cannot be assigned to a room.'
                ];
            }

            if (!$room) {
                return [
                    'success' => false,
                    'message' => 'Room not found.'
                ];
            }

            if (($room['status'] ?? 'available') === 'maintenance') {
                return [
                    'success' => false,
                    'message' => 'Students cannot be assigned to a room under maintenance.'
                ];
            }

            $capacity = (int) ($room['capacity'] ?? 0);
            $appConfig = require APP_ROOT . '/app/config/app.php';
            $configuredCapacity = (int) ($appConfig['advanced']['maximum_room_occupancy'] ?? 0);
            if ($configuredCapacity > 0) {
                $capacity = min($capacity, $configuredCapacity);
            }
            $occupied = (int) ($room['occupied'] ?? 0);

            if ($occupied >= $capacity) {
                return [
                    'success' => false,
                    'message' => 'Room is already full.'
                ];
            }

            if (!empty($student['roomId'])) {
                return [
                    'success' => false,
                    'message' => 'Student already has a room.'
                ];
            }

            $house = HouseService::find((string) ($room['houseId'] ?? ''));
            $studentGender = strtolower(trim((string) ($student['gender'] ?? '')));
            $houseGender = strtolower(trim((string) ($house['gender'] ?? '')));
            if ($studentGender !== '' && $houseGender !== '' && $studentGender !== $houseGender && $houseGender !== 'mixed') {
                return [
                    'success' => false,
                    'message' => 'Student gender does not match the selected house.'
                ];
            }

            $this->firebase->updateDocument(
                'students',
                $studentId,
                [
                    'roomId' => $roomId,
                    'houseId' => $room['houseId'] ?? null
                ]
            );

            $this->firebase->updateDocument(
                'rooms',
                $roomId,
                [
                    'occupied' => $occupied + 1,
                    'status' => ($occupied + 1 >= $capacity)
                        ? 'full'
                        : 'available'
                ]
            );

            $this->firebase->addDocument(
                'room_allocations',
                [
                    'studentId' => $studentId,
                    'roomId' => $roomId,
                    'houseId' => $room['houseId'] ?? null,
                    'allocatedBy' => $data['allocatedBy'] ?? null,
                    'status' => 'active'
                ]
            );

            return [
                'success' => true,
                'message' => 'Room allocated successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Room allocation failed: ' . $e->getMessage()
            ];
        }
    }

    public function deallocate(string $studentId): array
    {
        try {

            $studentService = new StudentService();
            $roomService = new RoomService();

            $student = $studentService->find($studentId);

            if (!$student || empty($student['roomId'])) {
                return [
                    'success' => false,
                    'message' => 'Student has no room.'
                ];
            }

            $roomId = $student['roomId'];

            $room = $roomService->find($roomId);

            if ($room) {
                $occupied = max(
                    0,
                    ((int) ($room['occupied'] ?? 0)) - 1
                );

                $roomService->update(
                    $roomId,
                    [
                        'occupied' => $occupied,
                        'status' => ($room['status'] ?? '') === 'maintenance'
                            ? 'maintenance'
                            : ($occupied > 0 ? 'occupied' : 'available')
                    ]
                );
            }

            foreach ($this->firebase->where('room_allocations', 'studentId', '=', $studentId) as $allocation) {
                if (($allocation['roomId'] ?? '') === $roomId && ($allocation['status'] ?? '') === 'active') {
                    $this->firebase->updateDocument('room_allocations', (string) $allocation['id'], [
                        'status' => 'ended',
                        'endedAt' => date('c')
                    ]);
                }
            }

            $studentService->update(
                $studentId,
                [
                    'roomId' => null
                ]
            );

            return [
                'success' => true,
                'message' => 'Room allocation removed successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to remove room allocation.'
            ];
        }
    }

    public function transfer(string $studentId, string $newRoomId, ?string $allocatedBy = null): array
    {
        try {
            $student = (new StudentService())->find($studentId);
            $newRoom = (new RoomService())->find($newRoomId);

            if (!$student || empty($student['roomId'])) {
                return ['success' => false, 'message' => 'Student has no current room.'];
            }
            if (in_array(strtolower((string) ($student['status'] ?? 'active')), ['inactive', 'suspended'], true)) {
                return ['success' => false, 'message' => 'Inactive or suspended students cannot be transferred.'];
            }
            if (!$newRoom) {
                return ['success' => false, 'message' => 'Destination room not found.'];
            }
            if ((string) $student['roomId'] === $newRoomId) {
                return ['success' => false, 'message' => 'Student is already assigned to this room.'];
            }
            if (($newRoom['status'] ?? 'available') === 'maintenance') {
                return ['success' => false, 'message' => 'Students cannot be transferred to a room under maintenance.'];
            }
            if ((int) ($newRoom['occupied'] ?? 0) >= (int) ($newRoom['capacity'] ?? 0)) {
                return ['success' => false, 'message' => 'Destination room is already full.'];
            }

            $house = HouseService::find((string) ($newRoom['houseId'] ?? ''));
            $studentGender = strtolower(trim((string) ($student['gender'] ?? '')));
            $houseGender = strtolower(trim((string) ($house['gender'] ?? '')));
            if ($studentGender !== '' && $houseGender !== '' && $studentGender !== $houseGender && $houseGender !== 'mixed') {
                return ['success' => false, 'message' => 'Student gender does not match the destination house.'];
            }

            $oldRoomId = (string) $student['roomId'];
            $oldRoom = (new RoomService())->find($oldRoomId);
            if ($oldRoom) {
                $oldOccupied = max(0, (int) ($oldRoom['occupied'] ?? 0) - 1);
                RoomService::update($oldRoomId, [
                    'occupied' => $oldOccupied,
                    'status' => $oldOccupied > 0 ? 'occupied' : 'available'
                ]);
            }

            $newOccupied = (int) ($newRoom['occupied'] ?? 0) + 1;
            RoomService::update($newRoomId, [
                'occupied' => $newOccupied,
                'status' => $newOccupied >= (int) ($newRoom['capacity'] ?? 0) ? 'full' : 'occupied'
            ]);
            StudentService::update($studentId, [
                'roomId' => $newRoomId,
                'houseId' => $newRoom['houseId'] ?? null
            ]);

            foreach ($this->firebase->where('room_allocations', 'studentId', '=', $studentId) as $allocation) {
                if (($allocation['status'] ?? '') === 'active') {
                    $this->firebase->updateDocument('room_allocations', (string) $allocation['id'], [
                        'status' => 'ended',
                        'endedAt' => date('c')
                    ]);
                }
            }
            $this->firebase->addDocument('room_allocations', [
                'studentId' => $studentId,
                'roomId' => $newRoomId,
                'houseId' => $newRoom['houseId'] ?? null,
                'allocatedBy' => $allocatedBy,
                'status' => 'active'
            ]);

            return ['success' => true, 'message' => 'Student transferred successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Room transfer failed: ' . $e->getMessage()];
        }
    }
}