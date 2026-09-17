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

    public function all(int $limit = 150): array
    {
        try {
            return $this->firebase->getCollection(
                $this->collection,
                [],
                $limit
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(?string $id): ?array
    {
        if (!$id) {
            return null;
        }

        try {
            return $this->firebase->getDocument($this->collection, $id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function update(string $id, array $data): bool
    {
        $this->firebase->updateDocument($this->collection, $id, $data);
        return true;
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

            $appConfig = require APP_ROOT . '/app/config/app/app.php';
            $status = !empty($appConfig['advanced']['visitor_approval_required']) ? 'pending' : 'registered';
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
                    'status' => $status,
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
                \COL_VISITOR_REQUESTS,
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

            $visitor = $this->find($id);
            if (!$visitor) {
                return ['success' => false, 'message' => 'Visitor not found.'];
            }
            if (($visitor['status'] ?? '') === 'pending') {
                return ['success' => false, 'message' => 'Visitor approval is required before check-in.'];
            }
            $appConfig = require APP_ROOT . '/app/config/app/app.php';
            $advanced = $appConfig['advanced'] ?? [];
            $now = new \DateTimeImmutable();
            $checkInTime = new \DateTimeImmutable($now->format('Y-m-d') . ' ' . ($advanced['check_in_time'] ?? '14:00'));
            $curfewTime = new \DateTimeImmutable($now->format('Y-m-d') . ' ' . ($advanced['curfew_time'] ?? '22:00'));
            if ($now < $checkInTime || $now >= $curfewTime) {
                return ['success' => false, 'message' => 'Visitor check-in is outside the permitted hours.'];
            }

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

            $studentIds = [];
            $studentNames = [];

            foreach ($students as $student) {
                $studentId = $student['studentId']
                    ?? $student['id']
                    ?? null;

                if (!$studentId) {
                    continue;
                }

                $studentIds[] = (string) $studentId;
                $studentNames[(string) $studentId] = trim(
                    ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')
                );
            }

            $studentIds = array_values(array_unique($studentIds));
            if ($studentIds === []) {
                return [];
            }

            $result = [];
            foreach (array_chunk($studentIds, 10) as $studentIdBatch) {
                $visitors = $this->firebase->getCollection(
                    $this->collection,
                    [['studentId', 'in', $studentIdBatch]],
                    150
                );

                foreach ($visitors as $visitor) {
                    $studentId = (string) ($visitor['studentId'] ?? '');
                    $visitor['studentName'] = $studentNames[$studentId] ?? '';
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

    public function count(): int
    {
        try {
            return $this->firebase->count($this->collection);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function todayCount(): int
    {
        try {
            $today = new \DateTimeImmutable('today');
            $tomorrow = $today->modify('+1 day');

            return $this->firebase->count(
                $this->collection,
                [
                    ['checkInTime', '>=', $today->format(DATE_ATOM)],
                    ['checkInTime', '<', $tomorrow->format(DATE_ATOM)],
                ]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function currentlyInside(): int
    {
        try {
            return $this->firebase->count(
                $this->collection,
                [['status', '=', 'inside']]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function pendingCount(): int
    {
        try {
            return $this->firebase->count(
                $this->collection,
                [['status', '=', 'pending']]
            );
        } catch (\Throwable $e) {
            return 0;
        }
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