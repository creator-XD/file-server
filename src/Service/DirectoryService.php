<?php

namespace App\Service;

use App\Storage\StorageInterface;

final class DirectoryService
{
    private StorageInterface $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function list(int $userId): array
    {
        return $this->storage->listDirectories($userId);
    }

    public function create(int $userId, string $directoryPath): void
    {
        $this->storage->createDirectory($userId, $directoryPath);
    }

    public function delete(int $userId, string $directoryPath): void
    {
        $this->storage->deleteDirectory($userId, $directoryPath);
    }
}