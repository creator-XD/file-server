<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HashStorageKeyTest extends TestCase
{
    public function testSameContentHasSameHash(): void
    {
        $content1 = 'same file content';
        $content2 = 'same file content';

        $hash1 = hash('sha256', $content1);
        $hash2 = hash('sha256', $content2);

        $this->assertSame($hash1, $hash2);
    }

    public function testDifferentContentHasDifferentHash(): void
    {
        $content1 = 'file content';
        $content2 = 'changed file content';

        $hash1 = hash('sha256', $content1);
        $hash2 = hash('sha256', $content2);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testStorageKeyIsBuiltFromHash(): void
    {
        $hash = 'abcdef1234567890';

        $storageKey = 'blobs/'
            . substr($hash, 0, 2)
            . '/'
            . substr($hash, 2, 2)
            . '/'
            . $hash;

        $this->assertSame('blobs/ab/cd/abcdef1234567890', $storageKey);
    }
}