<?php

/** @generate-class-entries */
namespace FlexId;

class Siphash
{
    public function __construct(string $key) {}
    public function hash(string $data, ?int $num = null): int {}
}

class Blake3
{
    public function __construct(string $key) {}

    /**
     * @return list<int>
     */
    public function hash(string $data, ?int $num = null, int $output_parts = 1): array {}
}

class Sparx64
{
    public function __construct(string $key) {}
    public function encrypt(int $num): int {}
    public function decrypt(int $data): int {}
}

class Sparx128
{
    public function __construct(string $key) {}
    /**
     * @return list<int>
     */
    public function encrypt(int $hipart, int $lowpart): array {}
    /**
     * @return list<int>
     */
    public function decrypt(int $hipart, int $lowpart): array {}
}

class Encoder
{
    public function __construct(string $alphabet, string $separator = ' ') {}
    public function encode(int $num): string {}
    public function decode(string $input): int {}

    public function doubleEncode(int $hipart, int $lowpart): string {}
    /**
     * @return list<int>
     */
    public function doubleDecode(string $input): array {}

    public function getMaxEncodedLength(): int {}
}

class HashEncoder
{
    public function __construct(string $alphabet, int $offset = 0) {}
    public function encode(int $num): string {}
    public function decode(string $input): int {}
    public function getMaxEncodedLength(): int {}

    /** @return array<string, mixed> */
    public function __serialize(): array {}

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void {}
}
