<?php

namespace App\Service;

use App\Entity\FileEntry;
use App\Entity\User;
use App\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DirectoryService
{
    private StorageInterface $storage;
    private EntityManagerInterface $entityManager;

    public function __construct(StorageInterface $storage, EntityManagerInterface $entityManager)
    {
        $this->storage = $storage;
        $this->entityManager = $entityManager;
    }

    public function list(int $userId): array
    {
        return $this->storage->listDirectories($userId);
    }

    public function create(int $userId, string $directoryPath): void
    {
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            throw new \InvalidArgumentException('Directory path is empty');
        }

        $this->storage->createDirectory($userId, $directoryPath);
    }

    public function delete(int $userId, string $directoryPath): void
    {
        $user = $this->getUser($userId);
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            throw new \InvalidArgumentException('Cannot delete root directory');
        }

        $files = $this->entityManager
            ->getRepository(FileEntry::class)
            ->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.path LIKE :pathPrefix')
            ->setParameter('user', $user)
            ->setParameter('pathPrefix', $directoryPath . '/%')
            ->getQuery()
            ->getResult();

        foreach ($files as $fileEntry) {
            $blob = $fileEntry->getBlob();

            $this->entityManager->remove($fileEntry);

            $blob->decrementRefCount();

            if ($blob->getRefCount() === 0) {
                $this->storage->deleteBlob($blob->getStorageKey());
                $this->entityManager->remove($blob);
            }
        }

        $this->storage->deleteDirectory($userId, $directoryPath);

        $this->entityManager->flush();
    }

    private function getUser(int $userId): User
    {
        $user = $this->entityManager->find(User::class, $userId);

        if (!$user instanceof User) {
            throw new \RuntimeException('User not found');
        }

        return $user;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new \InvalidArgumentException('Invalid path');
            }
        }

        return implode('/', $parts);
    }
}