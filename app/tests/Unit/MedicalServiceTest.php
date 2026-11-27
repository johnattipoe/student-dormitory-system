<?php

namespace App\Tests\Unit;

use App\Services\FirebaseService;
use App\Services\MedicalService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../services/FirebaseService/FirebaseService.php';
require_once __DIR__ . '/../../services/MedicalService/MedicalService.php';

class MedicalServiceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testCountForStudentsChunksIdsAndIncludesAdditionalFilters(): void
    {
        $calls = [];
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count'])
            ->getMock();
        $firebase->expects(self::exactly(2))
            ->method('count')
            ->willReturnCallback(function (string $collection, array $wheres) use (&$calls): int {
                $calls[] = [$collection, $wheres];
                return count($calls);
            });

        $instance = new \ReflectionProperty(FirebaseService::class, 'instance');
        $instance->setAccessible(true);
        $previousInstance = $instance->getValue();
        $instance->setValue(null, $firebase);

        try {
            $count = (new MedicalService())->countForStudents(
                array_merge(range(1, 30), ['31', '', '  ', '1']),
                [['severity', '=', 'emergency']]
            );
        } finally {
            $instance->setValue(null, $previousInstance);
        }

        self::assertSame(3, $count);
        self::assertSame('medical_records', $calls[0][0]);
        self::assertSame(['studentId', 'in', array_map('strval', range(1, 30))], $calls[0][1][0]);
        self::assertSame(['studentId', 'in', ['31']], $calls[1][1][0]);
        self::assertSame(['severity', '=', 'emergency'], $calls[0][1][1]);
        self::assertSame(['severity', '=', 'emergency'], $calls[1][1][1]);
    }

    #[RunInSeparateProcess]
    public function testCountForStudentsReturnsZeroWithoutQueryingForNoIds(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['count'])
            ->getMock();
        $firebase->expects(self::never())->method('count');

        $instance = new \ReflectionProperty(FirebaseService::class, 'instance');
        $instance->setAccessible(true);
        $previousInstance = $instance->getValue();
        $instance->setValue(null, $firebase);

        try {
            self::assertSame(0, (new MedicalService())->countForStudents(['', '  ']));
        } finally {
            $instance->setValue(null, $previousInstance);
        }
    }
}
