<?php

namespace App\Tests\Unit;

use App\Services\FirebaseService;
use App\Services\StudentService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

if (!defined('COL_STUDENTS')) {
    define('COL_STUDENTS', 'students');
}

require_once __DIR__ . '/../../services/FirebaseService/FirebaseService.php';
require_once __DIR__ . '/../../services/StudentService/StudentService.php';

class StudentServiceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testFindForUserUsesSingleEmailFilteredLookupWhenIdIsUnavailable(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocument', 'getCollection'])
            ->getMock();
        $firebase->expects(self::once())
            ->method('getDocument')
            ->with('students', 'auth-user-1')
            ->willReturn(null);
        $firebase->expects(self::once())
            ->method('getCollection')
            ->with('students', [['email', '=', 'student@example.com']], 1, 0, false)
            ->willReturn([['id' => 'student-document-1', 'email' => 'student@example.com']]);

        $instance = new \ReflectionProperty(FirebaseService::class, 'instance');
        $instance->setAccessible(true);
        $previousInstance = $instance->getValue();
        $instance->setValue(null, $firebase);

        try {
            $student = StudentService::findForUser([
                'uid' => 'auth-user-1',
                'email' => 'student@example.com',
            ]);
        } finally {
            $instance->setValue(null, $previousInstance);
        }

        self::assertSame('student-document-1', $student['id'] ?? null);
    }
}
