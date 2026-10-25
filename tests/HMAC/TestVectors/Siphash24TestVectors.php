<?php

declare(strict_types=1);

namespace Tests\HMAC\TestVectors;

trait Siphash24TestVectors
{
    /**
     * @var list<array<string, string|non-negative-int>>
     */
    private array $testVectors = [
        [
            'input_len' => 0,
            'output' => '310e0edd47db6f72',
        ],
        [
            'input_len' => 1,
            'output' => 'fd67dc93c539f874',
        ],
        [
            'input_len' => 2,
            'output' => '5a4fa9d909806c0d',
        ],
        [
            'input_len' => 3,
            'output' => '2d7efbd796666785',
        ],
        [
            'input_len' => 4,
            'output' => 'b7877127e09427cf',
        ],
        [
            'input_len' => 5,
            'output' => '8da699cd64557618',
        ],
        [
            'input_len' => 6,
            'output' => 'cee3fe586e46c9cb',
        ],
        [
            'input_len' => 7,
            'output' => '37d1018bf50002ab',
        ],
        [
            'input_len' => 8,
            'output' => '6224939a79f5f593',
        ],
        [
            'input_len' => 9,
            'output' => 'b0e4a90bdf82009e',
        ],
        [
            'input_len' => 10,
            'output' => 'f3b9dd94c5bb5d7a',
        ],
        [
            'input_len' => 11,
            'output' => 'a7ad6b22462fb3f4',
        ],
        [
            'input_len' => 12,
            'output' => 'fbe50e86bc8f1e75',
        ],
        [
            'input_len' => 13,
            'output' => '903d84c02756ea14',
        ],
        [
            'input_len' => 14,
            'output' => 'eef27a8e90ca23f7',
        ],
        [
            'input_len' => 15,
            'output' => 'e545be4961ca29a1',
        ],
        [
            'input_len' => 16,
            'output' => 'db9bc2577fcc2a3f',
        ],
    ];
}
