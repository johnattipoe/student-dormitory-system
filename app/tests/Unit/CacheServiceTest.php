<?php

namespace App\Tests\Unit;

use App\Services\CacheService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../services/CacheService/CacheService.php';

class CacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['REDIS_ENABLED'] = 'false';
    }

    #[RunInSeparateProcess]
    public function testRememberReusesTheValueWithinTheTtl(): void
    {
        $loads = 0;
        $key = 'cache-test:' . bin2hex(random_bytes(8));
        $loader = static function () use (&$loads): array {
            $loads++;
            return ['loaded' => true];
        };

        self::assertSame(['loaded' => true], CacheService::remember($key, 60, $loader));
        self::assertSame(['loaded' => true], CacheService::remember($key, 60, $loader));
        self::assertSame(1, $loads);
    }

    #[RunInSeparateProcess]
    public function testInvalidationAdvancesTheNamespaceGeneration(): void
    {
        $namespace = 'cache-test:' . bin2hex(random_bytes(8));
        $generation = CacheService::generation($namespace);

        CacheService::invalidate($namespace);

        self::assertSame($generation + 1, CacheService::generation($namespace));
    }
}
