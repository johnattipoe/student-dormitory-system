<?php

namespace App\Services;

use Throwable;

final class CacheService
{
    private static ?object $client = null;
    private static bool $redisDisabled = false;
    private static bool $failureLogged = false;
    private static array $localCache = [];
    private static array $generations = [];

    public static function remember(string $key, int $ttl, callable $loader): mixed
    {
        if ($ttl <= 0) {
            return $loader();
        }

        $redisKey = self::dataKey($key);
        $expiresAt = microtime(true) + $ttl;

        if (isset(self::$localCache[$redisKey]) && self::$localCache[$redisKey]['expiresAt'] > microtime(true)) {
            return self::$localCache[$redisKey]['value'];
        }
        unset(self::$localCache[$redisKey]);

        $client = self::client();
        if ($client !== null) {
            try {
                $payload = $client->get($redisKey);
                if (is_string($payload)) {
                    $decoded = @unserialize($payload, ['allowed_classes' => false, 'max_depth' => 64]);
                    if (
                        is_array($decoded)
                        && array_key_exists('value', $decoded)
                        && self::isCacheable($decoded['value'])
                    ) {
                        self::$localCache[$redisKey] = [
                            'expiresAt' => $expiresAt,
                            'value' => $decoded['value'],
                        ];
                        return $decoded['value'];
                    }

                    error_log('Redis cache entry could not be decoded; treating it as a cache miss.');
                    $client->del([$redisKey]);
                }
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        $value = $loader();
        self::$localCache[$redisKey] = [
            'expiresAt' => $expiresAt,
            'value' => $value,
        ];

        if ($client !== null && !self::$redisDisabled && self::isCacheable($value)) {
            try {
                $client->setex($redisKey, $ttl, serialize(['value' => $value]));
            } catch (Throwable $exception) {
                self::disableRedis($exception);
            }
        }

        return $value;
    }

    public static function generation(string $namespace): int
    {
        if (array_key_exists($namespace, self::$generations)) {
            return self::$generations[$namespace];
        }

        $client = self::client();
        if ($client === null) {
            return self::$generations[$namespace] = 0;
        }

        try {
            $generation = $client->get(self::generationKey($namespace));
            return self::$generations[$namespace] = max(0, (int) $generation);
        } catch (Throwable $exception) {
            self::disableRedis($exception);
            return self::$generations[$namespace] = 0;
        }
    }

    public static function invalidate(string $namespace): void
    {
        $client = self::client();
        if ($client === null) {
            self::$generations[$namespace] = self::generation($namespace) + 1;
            return;
        }

        try {
            self::$generations[$namespace] = (int) $client->incr(self::generationKey($namespace));
        } catch (Throwable $exception) {
            self::disableRedis($exception);
            self::$generations[$namespace] = self::generation($namespace) + 1;
        }
    }

    private static function client(): ?object
    {
        if (self::$redisDisabled) {
            return null;
        }

        $enabled = $_ENV['REDIS_ENABLED'] ?? getenv('REDIS_ENABLED') ?? 'true';
        if (!filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            self::$redisDisabled = true;
            return null;
        }

        if (self::$client !== null) {
            return self::$client;
        }

        $parameters = [
            'scheme' => 'tcp',
            'host' => (string) ($_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: '127.0.0.1'),
            'port' => max(1, (int) ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6380)),
            'database' => max(0, (int) ($_ENV['REDIS_DATABASE'] ?? getenv('REDIS_DATABASE') ?: 0)),
            'timeout' => 1.0,
            'read_write_timeout' => 1.0,
        ];
        $password = trim((string) ($_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD') ?: ''));
        if ($password !== '') {
            $parameters['password'] = $password;
        }

        try {
            self::$client = new \Predis\Client($parameters);
        } catch (Throwable $exception) {
            self::disableRedis($exception);
        }

        return self::$client;
    }

    private static function isCacheable(mixed $value, int $depth = 0): bool
    {
        if ($depth > 64 || is_object($value) || is_resource($value)) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::isCacheable($item, $depth + 1)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function dataKey(string $key): string
    {
        $prefix = trim((string) ($_ENV['REDIS_CACHE_PREFIX'] ?? getenv('REDIS_CACHE_PREFIX') ?: 'student-dormitory'));
        return $prefix . ':data:' . hash('sha256', $key);
    }

    private static function generationKey(string $namespace): string
    {
        $prefix = trim((string) ($_ENV['REDIS_CACHE_PREFIX'] ?? getenv('REDIS_CACHE_PREFIX') ?: 'student-dormitory'));
        return $prefix . ':generation:' . hash('sha256', $namespace);
    }

    private static function disableRedis(Throwable $exception): void
    {
        self::$client = null;
        self::$redisDisabled = true;

        if (!self::$failureLogged) {
            error_log('Redis cache unavailable; this request will use Firestore directly (' . get_class($exception) . ': ' . $exception->getMessage() . ').');
            self::$failureLogged = true;
        }
    }
}
