<?php

namespace App\Service;

use App\Storage\StorageInterface;
use Psr\Http\Message\UploadedFileInterface;

final class FileService
{
    private StorageInterface $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function upload(int $userId, string $directoryPath, UploadedFileInterface $file): string
    {
        return $this->storage->uploadFile($userId, $directoryPath, $file);
    }

    public function download(int $userId, string $filePath): string
    {
        return $this->storage->readFile($userId, $filePath);
    }

    public function rename(int $userId, string $oldPath, string $newName): void
    {
        $this->storage->renameFile($userId, $oldPath, $newName);
    }

    public function replace(int $userId, string $filePath, UploadedFileInterface $newFile): void
    {
        $this->storage->replaceFile($userId, $filePath, $newFile);
    }
}