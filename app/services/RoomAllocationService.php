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

            if (!$room) {
                return [
                    'success' => false,
                    'message' => 'Room not found.'
                ];
            }

            $capacity = (int) ($room['capacity'] ?? 0);
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
                        'status' => 'available'
                    ]
                );
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
}