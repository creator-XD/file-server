<?php

namespace App\Storage;

use Psr\Http\Message\UploadedFileInterface;

interface StorageInterface
{
    public function listDirectories(int $userId): array;

    public function createDirectory(int $userId, string $directoryPath): void;

    public function deleteDirectory(int $userId, string $directoryPath): void;

    public function uploadFile(int $userId, string $directoryPath, UploadedFileInterface $file): string;

    public function readFile(int $userId, string $filePath): string;

    public function renameFile(int $userId, string $oldPath, string $newName): void;

    public function replaceFile(int $userId, string $filePath, UploadedFileInterface $newFile): void;
}