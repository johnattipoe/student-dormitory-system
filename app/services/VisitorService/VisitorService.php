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

    public function byStatus(string $status, int $limit = 150): array
    {
        $status = trim($status);
        if ($status === '') {
            return [];
        }

        try {
            return $this->firebase->getCollection(
                $this->collection,
                [['status', '=', $status]],
                max(1, min(150, $limit)),
                0,
                false,
                [['createdAt', 'DESC']]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function forIds(array $visitorIds, int $limit = 150): array
    {
        $visitorIds = array_values(array_unique(array_filter(array_map(
            static fn($visitorId): string => trim((string) $visitorId),
            $visitorIds
        ))));
        if ($visitorIds === []) {
            return [];
        }

        $limit = max(1, min(150, $limit));
        $visitorsById = [];
        foreach (array_chunk($visitorIds, 30) as $visitorIdBatch) {
            $remainingLimit = $limit - count($visitorsById);
            if ($remainingLimit <= 0) {
                break;
            }

            foreach ($this->firebase->getCollection(
                $this->collection,
                [['__name__', 'in', $visitorIdBatch]],
                $remainingLimit,
                0,
                false
            ) as $visitor) {
                $id = (string) ($visitor['id'] ?? '');
                if ($id !== '') {
                    $visitorsById[$id] = $visitor;
                }
            }
        }

        return array_values($visitorsById);
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

    public function findForHouse(?string $id, ?string $houseId): ?array
    {
        $id = trim((string) $id);
        $houseId = trim((string) $houseId);
        if ($id === '' || $houseId === '') {
            return null;
        }

        $visitor = $this->find($id);
        if ($visitor === null || empty($visitor['studentId'])) {
            return null;
        }

        try {
            $studentId = (string) $visitor['studentId'];
            $student = $this->firebase->getDocument('students', $studentId);
            if ($student !== null) {
                $studentHouseId = (string) (
                    $student['houseId']
                    ?? $student['house_id']
                    ?? $student['assignedHouseId']
                    ?? $student['assigned_house_id']
                    ?? ''
                );
                if ($studentHouseId === $houseId) {
                    return $visitor;
                }
            }

            $students = $this->firebase->getCollection(
                'students',
                [['houseId', '=', $houseId]]
            );
            foreach ($students as $student) {
                $studentId = (string) ($student['studentId'] ?? $student['id'] ?? '');
                if ($studentId !== '' && $studentId === (string) $visitor['studentId']) {
                    return $visitor;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
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

    public function recentStudentVisitors(?string $studentId, int $limit = 5): array
    {
        if (!$studentId) {
            return [];
        }

        return $this->firebase->getCollection(
            $this->collection,
            [['studentId', '=', $studentId]],
            max(1, min(50, $limit)),
            0,
            false,
            [['createdAt', 'DESC']]
        );
    }

    public function countForStudent(?string $studentId): int
    {
        return $studentId
            ? $this->firebase->count($this->collection, [['studentId', '=', $studentId]])
            : 0;
    }

    public function byHouse(?string $houseId, ?array $houseStudents = null): array
    {
        if (!$houseId) {
            return [];
        }

        try {
            $students = $houseStudents ?? $this->firebase->getCollection(
                'students',
                [['houseId', '=', $houseId]]
            );

            $studentIds = [];
            $studentNames = [];
            $studentAdmissionNumbers = [];

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
                $studentAdmissionNumbers[(string) $studentId] = (string) ($student['admissionNo'] ?? '');
            }

            $studentIds = array_values(array_unique($studentIds));
            if ($studentIds === []) {
                return [];
            }

            $result = [];
            foreach (array_chunk($studentIds, 30) as $studentIdBatch) {
                $visitors = $this->firebase->getCollection(
                    $this->collection,
                    [['studentId', 'in', $studentIdBatch]],
                    150
                );

                foreach ($visitors as $visitor) {
                    $studentId = (string) ($visitor['studentId'] ?? '');
                    $visitor['studentName'] = $studentNames[$studentId] ?? '';
                    $visitor['studentAdmissionNo'] = $studentAdmissionNumbers[$studentId] ?? '';
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
            error_log('Visitor count unavailable: ' . $e->getMessage());
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
            error_log('Today visitor count unavailable: ' . $e->getMessage());
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
            error_log('Currently inside visitor count unavailable: ' . $e->getMessage());
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
            error_log('Pending visitor count unavailable: ' . $e->getMessage());
            return 0;
        }
    }

    public function todayByHouse(?string $houseId): array
    {
        if (!$houseId) {
            return [];
        }

        $today = date('Y-m-d');
        $students = $this->firebase->getCollection(
            'students',
            [['houseId', '=', $houseId]]
        );
        $studentNames = [];
        foreach ($students as $student) {
            $studentId = (string) ($student['studentId'] ?? $student['id'] ?? '');
            if ($studentId !== '') {
                $studentNames[$studentId] = trim(
                    (string) ($student['firstName'] ?? '') . ' ' . (string) ($student['lastName'] ?? '')
                );
            }
        }

        if ($studentNames === []) {
            return [];
        }

        $todayVisitors = [];
        foreach (array_chunk(array_keys($studentNames), 30) as $studentIdBatch) {
            $visitors = $this->firebase->getCollection(
                $this->collection,
                [
                    ['studentId', 'in', $studentIdBatch],
                    ['checkInTime', '>=', $today],
                ],
                150,
                0,
                false
            );

            foreach ($visitors as $visitor) {
                $checkInTime = (string) ($visitor['checkInTime'] ?? '');
                $studentId = (string) ($visitor['studentId'] ?? '');
                if (!str_starts_with($checkInTime, $today) || !isset($studentNames[$studentId])) {
                    continue;
                }

                $visitor['studentName'] = $studentNames[$studentId];
                $todayVisitors[] = $visitor;
            }
        }

        return $todayVisitors;
    }
}