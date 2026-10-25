<?php

declare(strict_types=1);

namespace Tests\HMAC\TestVectors;

trait Blake3TestVectors
{
    /**
     * @var list<array<string, string|non-negative-int>>
     */
    private array $testVectors = [
        [
            'input_len' => 0,
            'output' => '92b2b75604ed3c761f9d6f62392c8a9227ad0ea3f09573e783f1498a4ed60d26',
        ],
        [
            'input_len' => 1,
            'output' => '6d7878dfff2f485635d39013278ae14f1454b8c0a3a2d34bc1ab38228a80c95b',
        ],
        [
            'input_len' => 2,
            'output' => '5392ddae0e0a69d5f40160462cbd9bd889375082ff224ac9c758802b7a6fd20a',
        ],
        [
            'input_len' => 3,
            'output' => '39e67b76b5a007d4921969779fe666da67b5213b096084ab674742f0d5ec62b9',
        ],
        [
            'input_len' => 4,
            'output' => '7671dde590c95d5ac9616651ff5aa0a27bee5913a348e053b8aa9108917fe070',
        ],
        [
            'input_len' => 5,
            'output' => '73ac69eecf286894d8102018a6fc729f4b1f4247d3703f69bdc6a5fe3e0c8461',
        ],
        [
            'input_len' => 6,
            'output' => '82d3199d0013035682cc7f2a399d4c212544376a839aa863a0f4c91220ca7a6d',
        ],
        [
            'input_len' => 7,
            'output' => 'af0a7ec382aedc0cfd626e49e7628bc7a353a4cb108855541a5651bf64fbb28a',
        ],
        [
            'input_len' => 8,
            'output' => 'be2f5495c61cba1bb348a34948c004045e3bd4dae8f0fe82bf44d0da245a0600',
        ],
        [
            'input_len' => 63,
            'output' => 'bb1eb5d4afa793c1ebdd9fb08def6c36d10096986ae0cfe148cd101170ce37ae',
        ],
        [
            'input_len' => 64,
            'output' => 'ba8ced36f327700d213f120b1a207a3b8c04330528586f414d09f2f7d9ccb7e6',
        ],
        [
            'input_len' => 65,
            'output' => 'c0a4edefa2d2accb9277c371ac12fcdbb52988a86edc54f0716e1591b4326e72',
        ],
        [
            'input_len' => 127,
            'output' => 'c64200ae7dfaf35577ac5a9521c47863fb71514a3bcad18819218b818de85818',
        ],
        [
            'input_len' => 128,
            'output' => 'b04fe15577457267ff3b6f3c947d93be581e7e3a4b018679125eaf86f6a628ec',
        ],
        [
            'input_len' => 129,
            'output' => 'd4a64dae6cdccbac1e5287f54f17c5f985105457c1a2ec1878ebd4b57e20d38f',
        ],
        [
            'input_len' => 1023,
            'output' => 'c951ecdf03288d0fcc96ee3413563d8a6d3589547f2c2fb36d9786470f1b9d6e',
        ],
        [
            'input_len' => 1024,
            'output' => '75c46f6f3d9eb4f55ecaaee480db732e6c2105546f1e675003687c31719c7ba4',
        ],

    ];
}
