<?php

declare(strict_types=1);

namespace Tests\Internal;

use Predis\Client;

trait hasRedisClient
{
    private function getRedisClient(): \Redis
    {
        return new \Redis(
            [
                'host' => (string) \getenv('TESTING_REDIS_HOST'),
                'port' => (int) \getenv('TESTING_REDIS_PORT'),
                'connectTimeout' => 1,
                'readTimeout' => 1,
            ],
        );
    }

    private function getPRedisClient(): Client
    {
        return new Client(
            [
                'host' => (string) \getenv('TESTING_REDIS_HOST'),
                'port' => (int) \getenv('TESTING_REDIS_PORT'),
                'timeout' => 1,
                'read_write_timeout' => 1,
            ],
        );
    }

    private function getNonReachableRedisClient(): \Redis
    {
        return new \Redis(
            [
                'host' => (string) \getenv('TESTING_REDIS_HOST'),
                'port' => 1111,
                'connectTimeout' => 1,
                'readTimeout' => 1,
            ],
        );
    }
}
