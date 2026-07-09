<?php

namespace Tests\Unit;

use App\Storage\LocalStorage;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Локальное хранилище')]
final class LocalStorageTest extends TestCase
{
    private string $tempDir;
    private LocalStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/file_server_test_' . uniqid();

        mkdir($this->tempDir, 0777, true);

        $this->storage = new LocalStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectoryRecursive($this->tempDir);
    }

    #[TestDox('Создание и получение списка директорий')]
    public function testCreateAndListDirectory(): void
    {
        $this->storage->createDirectory(1, 'docs');

        $directories = $this->storage->listDirectories(1);

        $this->assertContains('docs', $directories);
    }

    #[TestDox('Создание вложенной директории')]
    public function testCreateNestedDirectory(): void
    {
        $this->storage->createDirectory(1, 'docs/reports');

        $directories = $this->storage->listDirectories(1);

        $this->assertContains('docs', $directories);
        $this->assertContains('docs/reports', $directories);
    }

    #[TestDox('Нельзя создать директорию с некорректным путём')]
    public function testCannotCreateDirectoryWithInvalidPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->storage->createDirectory(1, '../bad');
    }

    #[TestDox('Сохранение, чтение и удаление blob')]
    public function testSaveReadAndDeleteBlob(): void
    {
        $storageKey = 'blobs/ab/cd/test-hash';
        $content = 'Hello blob';

        $this->storage->saveBlob($storageKey, $content);

        $this->assertTrue($this->storage->blobExists($storageKey));
        $this->assertSame($content, $this->storage->readBlob($storageKey));

        $this->storage->deleteBlob($storageKey);

        $this->assertFalse($this->storage->blobExists($storageKey));
    }

    #[TestDox('Чтение отсутствующего blob выбрасывает исключение')]
    public function testReadMissingBlobThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->storage->readBlob('blobs/missing/file');
    }

    private function deleteDirectoryRecursive(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory), ['.', '..']);

        foreach ($items as $item) {
            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
