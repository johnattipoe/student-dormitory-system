<?php

namespace App\Tests\Unit;

use App\Services\AttendanceService;
use App\Services\FirebaseService;
use App\Services\IncidentService;
use App\Services\MedicalService;
use App\Services\ParentMessageService;
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\VisitorService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

if (!defined('COL_ATTENDANCE')) {
    define('COL_ATTENDANCE', 'attendance');
}
if (!defined('COL_STUDENTS')) {
    define('COL_STUDENTS', 'students');
}
if (!defined('COL_PARENT_MESSAGES')) {
    define('COL_PARENT_MESSAGES', 'parent_messages');
}

require_once __DIR__ . '/../../services/FirebaseService/FirebaseService.php';
require_once __DIR__ . '/../../services/AttendanceService/AttendanceService.php';
require_once __DIR__ . '/../../services/IncidentService/IncidentService.php';
require_once __DIR__ . '/../../services/MedicalService/MedicalService.php';
require_once __DIR__ . '/../../services/ParentMessageService/ParentMessageService.php';
require_once __DIR__ . '/../../services/StudentService/StudentService.php';
require_once __DIR__ . '/../../services/UserService/UserService.php';
require_once __DIR__ . '/../../services/VisitorService/VisitorService.php';

class TargetedRecordLookupTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testVisitorHouseLookupReadsOneVisitorAndChecksHouseMembership(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument', 'getCollection'])
            ->getMock();
        $firebase->method('getDocument')
            ->willReturnCallback(static fn(string $collection, string $id): ?array => match ([$collection, $id]) {
                ['visitors', 'visitor-1'] => ['id' => 'visitor-1', 'studentId' => 'student-1'],
                ['students', 'student-1'] => ['id' => 'student-1', 'houseId' => 'house-1'],
                default => null,
            });
        $firebase->expects(self::never())->method('getCollection');

        $visitor = $this->withFirebase($firebase, static fn(): ?array =>
            (new VisitorService())->findForHouse('visitor-1', 'house-1')
        );

        self::assertSame('visitor-1', $visitor['id'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testVisitorHouseLookupRejectsVisitorFromAnotherHouse(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument', 'getCollection'])
            ->getMock();
        $firebase->method('getDocument')
            ->willReturnCallback(static fn(string $collection, string $id): ?array => match ([$collection, $id]) {
                ['visitors', 'visitor-1'] => ['id' => 'visitor-1', 'studentId' => 'student-1'],
                ['students', 'student-1'] => ['id' => 'student-1', 'houseId' => 'house-1'],
                default => null,
            });
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with('students', [['houseId', '=', 'house-2']])
            ->willReturn([['id' => 'student-2']]);

        $visitor = $this->withFirebase($firebase, static fn(): ?array =>
            (new VisitorService())->findForHouse('visitor-1', 'house-2')
        );

        self::assertNull($visitor);
    }

    #[RunInSeparateProcess]
    public function testIncidentHouseLookupUsesTheAssignedHouseStudentRoster(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument', 'getCollection'])
            ->getMock();
        $firebase->method('getDocument')
            ->willReturnCallback(static fn(string $collection, string $id): ?array => match ([$collection, $id]) {
                ['incidents', 'incident-1'] => ['id' => 'incident-1', 'studentId' => 'student-1'],
                ['students', 'student-1'] => ['studentId' => 'student-1', 'houseId' => 'house-1'],
                default => null,
            });
        $firebase->expects(self::never())->method('getCollection');

        $incident = $this->withFirebase($firebase, static fn(): ?array =>
            (new IncidentService())->findForHouse('incident-1', 'house-1')
        );

        self::assertSame('incident-1', $incident['id'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testIncidentStudentLookupRejectsRecordsOwnedByAnotherStudent(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getDocument')
            ->with('incidents', 'incident-1')
            ->willReturn(['id' => 'incident-1', 'studentId' => 'student-1']);

        $incident = $this->withFirebase($firebase, static fn(): ?array =>
            (new IncidentService())->findForStudent('incident-1', 'student-2')
        );

        self::assertNull($incident);
    }

    #[RunInSeparateProcess]
    public function testAttendanceStudentLookupUsesDocumentIdAndVerifiesOwner(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getDocument')
            ->with('attendance', 'attendance-1')
            ->willReturn(['id' => 'attendance-1', 'studentId' => 'student-1']);

        $record = $this->withFirebase($firebase, static fn(): ?array =>
            AttendanceService::findForStudent('attendance-1', 'student-1')
        );

        self::assertSame('attendance-1', $record['id'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testAttendanceMarkLookupFiltersByStudentAndDate(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with(
                'attendance',
                [['studentId', '=', 'student-1'], ['date', '=', '2026-11-25']],
                1,
                0,
                false
            )
            ->willReturn([['id' => 'attendance-1', 'studentId' => 'student-1', 'date' => '2026-11-25']]);

        $record = $this->withFirebase($firebase, static fn(): ?array =>
            AttendanceService::findForStudentOnDate('student-1', '2026-11-25')
        );

        self::assertSame('attendance-1', $record['id'] ?? null);
    }

    #[RunInSeparateProcess]
    public function testStudentLookupByIdsUsesDocumentIdBatches(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::exactly(2))
            ->method('getCollection')
            ->willReturnCallback(static function (
                string $collection,
                array $wheres,
                int $limit,
                int $offset,
                bool $respectRequestPagination
            ): array {
                self::assertSame('students', $collection);
                self::assertSame(0, $offset);
                self::assertFalse($respectRequestPagination);
                self::assertSame('__name__', $wheres[0][0]);
                self::assertSame('in', $wheres[0][1]);
                self::assertGreaterThan(0, count($wheres[0][2]));
                self::assertLessThanOrEqual(30, count($wheres[0][2]));

                return array_map(
                    static fn(string $id): array => ['id' => $id],
                    $wheres[0][2]
                );
            });

        $studentIds = array_map(static fn(int $id): string => 'student-' . $id, range(1, 31));
        $students = $this->withFirebase($firebase, static fn(): array =>
            StudentService::forIds($studentIds)
        );

        self::assertCount(31, $students);
    }

    #[RunInSeparateProcess]
    public function testVisitorHouseListingReusesProvidedStudentRoster(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with('visitors', [['studentId', 'in', ['student-1']]], 150)
            ->willReturn([['id' => 'visitor-1', 'studentId' => 'student-1']]);

        $visitors = $this->withFirebase($firebase, static fn(): array =>
            (new VisitorService())->byHouse('house-1', [[
                'id' => 'student-1',
                'firstName' => 'Ari',
                'lastName' => 'Lee',
                'admissionNo' => 'A1',
            ]])
        );

        self::assertSame('Ari Lee', $visitors[0]['studentName']);
        self::assertSame('A1', $visitors[0]['studentAdmissionNo']);
    }

    #[RunInSeparateProcess]
    public function testTodayVisitorsAreQueriedByHouseStudentsAndDate(): void
    {
        $today = date('Y-m-d');
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection', 'getDocument'])
            ->getMock();
        $firebase->expects(self::never())->method('getDocument');
        $firebase->expects(self::exactly(2))
            ->method('getCollection')
            ->willReturnCallback(static function (
                string $collection,
                array $wheres,
                int $limit = 150,
                int $offset = 0,
                bool $respectRequestPagination = true
            ) use ($today): array {
                if ($collection === 'students') {
                    self::assertSame([['houseId', '=', 'house-1']], $wheres);
                    return [['id' => 'student-1', 'firstName' => 'Ari', 'lastName' => 'Lee']];
                }

                self::assertSame('visitors', $collection);
                self::assertSame([
                    ['studentId', 'in', ['student-1']],
                    ['checkInTime', '>=', $today],
                ], $wheres);
                self::assertSame(150, $limit);
                self::assertSame(0, $offset);
                self::assertFalse($respectRequestPagination);

                return [
                    ['id' => 'visitor-today', 'studentId' => 'student-1', 'checkInTime' => $today . 'T10:00:00+00:00'],
                    ['id' => 'visitor-other-day', 'studentId' => 'student-1', 'checkInTime' => '2026-11-25T10:00:00+00:00'],
                ];
            });

        $visitors = $this->withFirebase($firebase, static fn(): array =>
            (new VisitorService())->todayByHouse('house-1')
        );

        self::assertCount(1, $visitors);
        self::assertSame('Ari Lee', $visitors[0]['studentName']);
    }

    #[RunInSeparateProcess]
    public function testIncidentHouseListingReusesProvidedStudentRoster(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with('incidents', [['studentId', 'in', ['student-1']]], 150)
            ->willReturn([['id' => 'incident-1', 'studentId' => 'student-1']]);

        $incidents = $this->withFirebase($firebase, static fn(): array =>
            (new IncidentService())->byHouse('house-1', false, [[
                'id' => 'student-1',
                'firstName' => 'Ari',
                'lastName' => 'Lee',
            ]])
        );

        self::assertSame('Ari Lee', $incidents[0]['studentName']);
    }

    #[RunInSeparateProcess]
    public function testEmergencyMedicalCountsRespectFirestoreDisjunctionLimit(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count'])
            ->getMock();
        $firebase->expects(self::exactly(2))
            ->method('count')
            ->willReturnCallback(static function (string $collection, array $wheres): int {
                self::assertSame('medical_records', $collection);
                self::assertSame('studentId', $wheres[0][0]);
                self::assertSame('in', $wheres[0][1]);
                self::assertLessThanOrEqual(10, count($wheres[0][2]));
                self::assertSame(['severity', 'in', ['emergency', 'Emergency', 'EMERGENCY']], $wheres[1]);
                return 1;
            });

        $studentIds = array_map(static fn(int $id): string => 'student-' . $id, range(1, 11));
        $count = $this->withFirebase($firebase, static fn(): int =>
            (new MedicalService())->countForStudentsBySeverity(
                $studentIds,
                ['emergency', 'Emergency', 'EMERGENCY']
            )
        );

        self::assertSame(2, $count);
    }

    #[RunInSeparateProcess]
    public function testHouseAssignmentOptionsFilterUsersByAllowedRoles(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with(
                'users',
                [['role', 'in', ['house_master', 'house_mistress']]],
                150,
                0,
                false
            )
            ->willReturn([
                ['id' => 'user-1', 'role' => 'house_master'],
                ['id' => 'user-2', 'role' => 'house_mistress'],
            ]);

        $users = $this->withFirebase($firebase, static fn(): array =>
            (new UserService())->byRoles(['house_master', 'house_mistress'])
        );

        self::assertCount(2, $users);
    }

    #[RunInSeparateProcess]
    public function testVisitorIdsAreResolvedWithBatchedDocumentQueries(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with(
                'visitors',
                [['__name__', 'in', ['visitor-1', 'visitor-2']]],
                150,
                0,
                false
            )
            ->willReturn([
                ['id' => 'visitor-1', 'status' => 'pending'],
                ['id' => 'visitor-2', 'status' => 'inside'],
            ]);

        $visitors = $this->withFirebase($firebase, static fn(): array =>
            (new VisitorService())->forIds(['visitor-1', 'visitor-2'])
        );

        self::assertCount(2, $visitors);
    }

    #[RunInSeparateProcess]
    public function testParentMessagesAreQueriedByStudentIdBatches(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with(
                'parent_messages',
                [['studentId', 'in', ['student-1', 'student-2']]],
                150,
                0,
                false,
                [['createdAt', 'DESC']]
            )
            ->willReturn([
                ['id' => 'message-1', 'studentId' => 'student-1', 'createdAt' => '2026-11-27T00:00:00Z'],
                ['id' => 'message-2', 'studentId' => 'student-2', 'createdAt' => '2026-11-26T00:00:00Z'],
            ]);

        $messages = $this->withFirebase($firebase, static fn(): array =>
            (new ParentMessageService())->forStudentIds(['student-1', 'student-2'])
        );

        self::assertCount(2, $messages);
    }

    public function testFirestoreWhereUsesDocumentIdFieldPath(): void
    {
        $query = new class {
            public mixed $field = null;
            public mixed $operator = null;
            public mixed $value = null;

            public function where(mixed $field, mixed $operator, mixed $value): self
            {
                $this->field = $field;
                $this->operator = $operator;
                $this->value = $value;
                return $this;
            }
        };
        $firebase = (new \ReflectionClass(FirebaseService::class))->newInstanceWithoutConstructor();
        $applyWheres = new \ReflectionMethod(FirebaseService::class, 'applyWheres');
        $applyWheres->setAccessible(true);

        $applyWheres->invoke($firebase, $query, [['__name__', 'in', ['student-1']]]);

        self::assertInstanceOf(\Google\Cloud\Firestore\FieldPath::class, $query->field);
        self::assertSame('in', $query->operator);
        self::assertSame(['student-1'], $query->value);
    }

    private function withFirebase(FirebaseService $firebase, callable $callback): mixed
    {
        $instance = new \ReflectionProperty(FirebaseService::class, 'instance');
        $instance->setAccessible(true);
        $previousInstance = $instance->getValue();
        $instance->setValue(null, $firebase);

        try {
            return $callback();
        } finally {
            $instance->setValue(null, $previousInstance);
        }
    }
}
