<?php

namespace App\Tests\Unit;

use App\Services\StudentPromotionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../services/StudentPromotionService/StudentPromotionService.php';

class StudentPromotionServiceTest extends TestCase
{
    public function testAcademicYearMustContainConsecutiveYears(): void
    {
        self::assertTrue(StudentPromotionService::isValidAcademicYear('2026/2027'));
        self::assertFalse(StudentPromotionService::isValidAcademicYear('2026-2027'));
        self::assertFalse(StudentPromotionService::isValidAcademicYear('2026/2028'));
    }

    public function testRecognizesOnlyFormOneThroughThree(): void
    {
        self::assertSame(1, StudentPromotionService::gradeNumber('Form 1'));
        self::assertSame(2, StudentPromotionService::gradeNumber('SHS 2'));
        self::assertSame(3, StudentPromotionService::gradeNumber('3'));
        self::assertNull(StudentPromotionService::gradeNumber('Form 4'));
        self::assertNull(StudentPromotionService::gradeNumber('Science'));
    }

    public function testStudentGradeUsesFormLevelBeforeClassAndSupportsLegacyClassFallback(): void
    {
        self::assertSame(
            ['grade' => 2, 'value' => 'Form 2'],
            StudentPromotionService::studentGrade(['form' => 'Form 2', 'class' => 'SHS 1'])
        );
        self::assertSame(
            ['grade' => 1, 'value' => 'SHS 1'],
            StudentPromotionService::studentGrade(['class' => 'SHS 1'])
        );
        self::assertNull(StudentPromotionService::studentGrade(['form' => 'Form 4', 'class' => 'SHS 1']));
    }

    public function testSourceFormValidationIsIndependentFromAcademicYear(): void
    {
        self::assertTrue(StudentPromotionService::isValidSourceForm('Form 1'));
        self::assertTrue(StudentPromotionService::isValidSourceForm('Form 2'));
        self::assertTrue(StudentPromotionService::isValidSourceForm('Form 3'));
        self::assertFalse(StudentPromotionService::isValidSourceForm('SHS 1'));
        self::assertSame(2, StudentPromotionService::sourceFormGrade('Form 2'));
    }

    public function testAdvancesFormAndOnlyMatchingClassLabels(): void
    {
        self::assertSame('Form 2', StudentPromotionService::nextGradeValue('Form 1', 2));
        self::assertSame('SHS 3', StudentPromotionService::nextGradeValue('SHS 2', 3));
        self::assertSame('SHS 2', StudentPromotionService::nextClassValue('SHS 1', 1, 2));
        self::assertSame('2 SCIENCE 2', StudentPromotionService::nextClassValue('1 SCIENCE 2', 1, 2));
        self::assertSame('Science', StudentPromotionService::nextClassValue('Science', 1, 2));
        self::assertSame('SHS 1A', StudentPromotionService::nextClassValue('SHS 1A', 1, 2));
    }

    public function testBuildPlanSupportsRepeatingAndFormThreeGraduation(): void
    {
        $candidates = [
            [
                'id' => 'student-1',
                'name' => 'First Student',
                'admissionNo' => 'A001',
                'grade' => 1,
                'fromForm' => 'Form 1',
                'fromClass' => 'SHS 1',
                'status' => 'active',
            ],
            [
                'id' => 'student-3',
                'name' => 'Third Student',
                'admissionNo' => 'A003',
                'grade' => 3,
                'fromForm' => 'Form 3',
                'fromClass' => 'SHS 3',
                'status' => 'active',
            ],
        ];

        $plan = StudentPromotionService::buildPlan($candidates, [
            'student-1' => 'promote',
            'student-3' => 'graduate',
        ]);

        self::assertSame('Form 2', $plan[0]['toForm']);
        self::assertSame('SHS 2', $plan[0]['toClass']);
        self::assertSame('graduated', $plan[1]['toStatus']);
        self::assertSame('Form 3', $plan[1]['toForm']);
    }

    public function testBuildPlanRequiresAnOutcomeForEveryCandidate(): void
    {
        $this->expectException(\RuntimeException::class);
        StudentPromotionService::buildPlan([
            [
                'id' => 'student-1',
                'name' => 'First Student',
                'admissionNo' => 'A001',
                'grade' => 1,
                'fromForm' => 'Form 1',
                'fromClass' => 'SHS 1',
                'status' => 'active',
            ],
        ], []);
    }

    public function testFormThreeCannotBePromotedPastGraduation(): void
    {
        $this->expectException(\RuntimeException::class);
        StudentPromotionService::buildPlan([
            [
                'id' => 'student-3',
                'name' => 'Third Student',
                'admissionNo' => 'A003',
                'grade' => 3,
                'fromForm' => 'Form 3',
                'fromClass' => 'SHS 3',
                'status' => 'active',
            ],
        ], ['student-3' => 'promote']);
    }

    public function testPageAndServiceExposeBatchAndUndoControls(): void
    {
        $service = file_get_contents(__DIR__ . '/../../services/StudentPromotionService/StudentPromotionService.php');
        $page = file_get_contents(__DIR__ . '/../../../public/views/admin/students/promotion/index.php');

        self::assertIsString($service);
        self::assertIsString($page);
        self::assertStringContainsString('batchLimit', $page);
        self::assertStringContainsString('function undo(', $service);
        self::assertStringContainsString('Source form', $page);
        self::assertStringNotContainsString('name="academicYear"', $page);
    }
}
