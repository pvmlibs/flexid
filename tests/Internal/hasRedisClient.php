<?php

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
                'connectTimeout' => 2,
                'readTimeout' => 2,
            ],
        );
    }

    private function getPRedisClient(): Client
    {
        return new Client(
            [
                'host' => (string) \getenv('TESTING_REDIS_HOST'),
                'port' => (int) \getenv('TESTING_REDIS_PORT'),
                'timeout' => 2,
                'read_write_timeout' => 2,
            ],
        );
    }

    private function getNonReachableRedisClient(): \Redis
    {
        return new \Redis(
            [
                'host' => (string) \getenv('TESTING_REDIS_HOST'),
                'port' => 1111,
                'connectTimeout' => 2,
                'readTimeout' => 2,
            ],
        );
    }
}
