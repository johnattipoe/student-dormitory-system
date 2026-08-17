<?php

namespace App\Services;

class VisitorService
{
    private FirebaseService $firebase;

    private string $collection = 'visitors';

    public function __construct()
    {
        $this->firebase = FirebaseService::getInstance();
    }

    public function all(): array
    {
        try {
            return $this->firebase->getCollection(
                $this->collection
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function register(array $data): array
    {
        try {

            if (
                empty($data['visitorName']) ||
                empty($data['studentId'])
            ) {
                return [
                    'success' => false,
                    'message' => 'Visitor name and student are required.'
                ];
            }

            $id = $this->firebase->addDocument(
                $this->collection,
                [
                    'visitorName' => $data['visitorName'],
                    'phone' => $data['phone'] ?? '',
                    'studentId' => $data['studentId'],
                    'purpose' => $data['purpose'] ?? '',
                    'idType' => $data['idType'] ?? '',
                    'idNumber' => $data['idNumber'] ?? '',
                    'registeredBy' => $data['registeredBy'] ?? null,
                    'status' => 'registered',
                    'checkInTime' => null,
                    'checkOutTime' => null
                ]
            );

            return [
                'success' => true,
                'message' => 'Visitor registered successfully.',
                'id' => $id
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to register visitor: ' . $e->getMessage()
            ];
        }
    }

    public function request(array $data): array
    {
        try {

            $id = $this->firebase->addDocument(
                $this->collection,
                [
                    'studentId' => $data['studentId'] ?? null,
                    'visitorName' => $data['visitorName'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'relationship' => $data['relationship'] ?? '',
                    'visitDate' => $data['visitDate'] ?? '',
                    'purpose' => $data['purpose'] ?? '',
                    'status' => 'pending'
                ]
            );

            return [
                'success' => true,
                'message' => 'Visitor request submitted.',
                'id' => $id
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to submit visitor request.'
            ];
        }
    }

    public function checkIn(
        string $id,
        ?string $securityUser = null
    ): array {
        try {

            $this->firebase->updateDocument(
                $this->collection,
                $id,
                [
                    'status' => 'inside',
                    'checkInTime' => date('c'),
                    'checkedInBy' => $securityUser
                ]
            );

            return [
                'success' => true,
                'message' => 'Visitor checked in successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to check in visitor.'
            ];
        }
    }

    public function checkOut(
        string $id,
        ?string $securityUser = null
    ): array {
        try {

            $this->firebase->updateDocument(
                $this->collection,
                $id,
                [
                    'status' => 'checked_out',
                    'checkOutTime' => date('c'),
                    'checkedOutBy' => $securityUser
                ]
            );

            return [
                'success' => true,
                'message' => 'Visitor checked out successfully.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to check out visitor.'
            ];
        }
    }

    public function studentVisitors(?string $studentId): array
    {
        if (!$studentId) {
            return [];
        }

        try {
            return $this->firebase->getCollection(
                $this->collection,
                [
                    ['studentId', '=', $studentId]
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function byHouse(?string $houseId): array
    {
        if (!$houseId) {
            return [];
        }

        try {

            $students = $this->firebase->getCollection(
                'students',
                [
                    ['houseId', '=', $houseId]
                ]
            );

            $result = [];

            foreach ($students as $student) {

                $studentId = $student['studentId']
                    ?? $student['id']
                    ?? null;

                if (!$studentId) {
                    continue;
                }

                $visitors = $this->studentVisitors($studentId);

                foreach ($visitors as $visitor) {
                    $visitor['studentName'] =
                        ($student['firstName'] ?? '') . ' ' .
                        ($student['lastName'] ?? '');

                    $result[] = $visitor;
                }
            }

            return $result;

        } catch (\Throwable $e) {
            return [];
        }
    }

    public function history(): array
    {
        return $this->all();
    }

    public function todayCount(): int
    {
        $visitors = $this->all();

        $count = 0;
        $today = date('Y-m-d');

        foreach ($visitors as $visitor) {
            $time = $visitor['checkInTime'] ?? '';

            if ($time && str_starts_with($time, $today)) {
                $count++;
            }
        }

        return $count;
    }

    public function currentlyInside(): int
    {
        $visitors = $this->all();

        $count = 0;

        foreach ($visitors as $visitor) {
            if (($visitor['status'] ?? '') === 'inside') {
                $count++;
            }
        }

        return $count;
    }

    public function pendingCount(): int
    {
        $visitors = $this->all();

        $count = 0;

        foreach ($visitors as $visitor) {
            if (($visitor['status'] ?? '') === 'pending') {
                $count++;
            }
        }

        return $count;
    }

    public function todayByHouse(?string $houseId): array
    {
        $visitors = $this->byHouse($houseId);

        $today = date('Y-m-d');

        return array_values(
            array_filter(
                $visitors,
                function ($visitor) use ($today) {
                    $time = $visitor['checkInTime'] ?? '';
                    return $time && str_starts_with($time, $today);
                }
            )
        );
    }
}