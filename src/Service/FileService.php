<?php

namespace App\Service;

use App\Entity\FileBlob;
use App\Entity\FileEntry;
use App\Entity\User;
use App\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\UploadedFileInterface;

final class FileService
{
    private StorageInterface $storage;
    private EntityManagerInterface $entityManager;
    private UsageService $usageService;

    public function __construct(StorageInterface $storage, EntityManagerInterface $entityManager, UsageService $usageService)
    {
        $this->storage = $storage;
        $this->entityManager = $entityManager;
        $this->usageService = $usageService;
    }

    public function upload(int $userId, string $directoryPath, UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $user = $this->getUser($userId);

        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath !== '' && !$this->directoryExists($userId, $directoryPath)) {
            throw new \RuntimeException('Directory does not exist');
        }

        $fileName = $file->getClientFilename();

        if ($fileName === null || $fileName === '') {
            throw new \RuntimeException('File name is empty');
        }

        $this->validateFileName($fileName);

        $userFilePath = $directoryPath === ''
            ? $fileName
            : $directoryPath . '/' . $fileName;

        $existingFile = $this->entityManager
            ->getRepository(FileEntry::class)
            ->findOneBy([
                'user' => $user,
                'path' => $userFilePath,
            ]);

        if ($existingFile !== null) {
            throw new \RuntimeException('File already exists');
        }

        $content = $this->readUploadedFileContent($file);
        $hash = hash('sha256', $content);
        $size = strlen($content);
        $this->usageService->assertCanUpload(
            $user,
            $size
        );
        
        $mimeType = $file->getClientMediaType();

        $blob = $this->entityManager
            ->getRepository(FileBlob::class)
            ->findOneBy([
                'hash' => $hash,
            ]);

        if ($blob === null) {
            $storageKey = $this->buildBlobStorageKey($hash);

            if (!$this->storage->blobExists($storageKey)) {
                $this->storage->saveBlob($storageKey, $content);
            }

            $blob = new FileBlob(
                $hash,
                $storageKey,
                $size,
                $mimeType
            );

            $this->entityManager->persist($blob);
        } else {
            $blob->incrementRefCount();
        }

        $fileEntry = new FileEntry(
            $user,
            $blob,
            $userFilePath,
            $fileName,
            $size,
            $mimeType
        );

        $this->entityManager->persist($fileEntry);
        $this->entityManager->flush();

        return $fileName;
    }

    public function download(int $userId, string $filePath): string
    {
        $user = $this->getUser($userId);
        $filePath = $this->normalizePath($filePath);

        if ($filePath === '') {
            throw new \InvalidArgumentException('File path is empty');
        }

        $fileEntry = $this->entityManager
            ->getRepository(FileEntry::class)
            ->findOneBy([
                'user' => $user,
                'path' => $filePath,
            ]);

        if ($fileEntry === null) {
            throw new \RuntimeException('File not found');
        }

        return $this->storage->readBlob(
            $fileEntry->getBlob()->getStorageKey()
        );
    }

    public function rename(int $userId, string $oldPath, string $newName): void
    {
        $user = $this->getUser($userId);

        $oldPath = $this->normalizePath($oldPath);

        if ($oldPath === '') {
            throw new \InvalidArgumentException('Old path is empty');
        }

        $this->validateFileName($newName);

        $fileEntry = $this->entityManager
            ->getRepository(FileEntry::class)
            ->findOneBy([
                'user' => $user,
                'path' => $oldPath,
            ]);

        if ($fileEntry === null) {
            throw new \RuntimeException('File not found');
        }

        $directory = dirname($oldPath);

        if ($directory === '.' || $directory === '') {
            $newPath = $newName;
        } else {
            $newPath = $directory . '/' . $newName;
        }

        $existingFile = $this->entityManager
            ->getRepository(FileEntry::class)
            ->findOneBy([
                'user' => $user,
                'path' => $newPath,
            ]);

        if ($existingFile !== null) {
            throw new \RuntimeException('File with this name already exists');
        }

        $fileEntry->setPath($newPath);
        $fileEntry->setName($newName);

        $this->entityManager->flush();
    }

    public function replace(int $userId, string $filePath, UploadedFileInterface $newFile): void
    {
        if ($newFile->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $user = $this->getUser($userId);
        $filePath = $this->normalizePath($filePath);

        if ($filePath === '') {
            throw new \InvalidArgumentException('File path is empty');
        }

        $fileEntry = $this->entityManager
            ->getRepository(FileEntry::class)
            ->findOneBy([
                'user' => $user,
                'path' => $filePath,
            ]);

        if ($fileEntry === null) {
            throw new \RuntimeException('File not found');
        }

        $oldBlob = $fileEntry->getBlob();

        $content = $this->readUploadedFileContent($newFile);
        $hash = hash('sha256', $content);
        $size = strlen($content);
        $mimeType = $newFile->getClientMediaType();

        $newBlob = $this->entityManager
            ->getRepository(FileBlob::class)
            ->findOneBy([
                'hash' => $hash,
            ]);

        if ($newBlob === null) {
            $storageKey = $this->buildBlobStorageKey($hash);

            if (!$this->storage->blobExists($storageKey)) {
                $this->storage->saveBlob($storageKey, $content);
            }

            $newBlob = new FileBlob(
                $hash,
                $storageKey,
                $size,
                $mimeType
            );

            $this->entityManager->persist($newBlob);
        } else {
            $newBlob->incrementRefCount();
        }

        $fileEntry->setBlob($newBlob);
        $fileEntry->setSize($size);
        $fileEntry->setMimeType($mimeType);

        $oldBlob->decrementRefCount();

        if ($oldBlob->getRefCount() === 0) {
            $this->storage->deleteBlob($oldBlob->getStorageKey());
            $this->entityManager->remove($oldBlob);
        }

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

    private function readUploadedFileContent(UploadedFileInterface $file): string
    {
        $stream = $file->getStream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    private function buildBlobStorageKey(string $hash): string
    {
        return 'blobs/'
            . substr($hash, 0, 2)
            . '/'
            . substr($hash, 2, 2)
            . '/'
            . $hash;
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

    private function validateFileName(string $fileName): void
    {
        if (
            $fileName === '' ||
            str_contains($fileName, '/') ||
            str_contains($fileName, '\\') ||
            str_contains($fileName, '..')
        ) {
            throw new \InvalidArgumentException('Invalid file name');
        }
    }

    private function directoryExists(int $userId, string $directoryPath): bool
    {
        $directories = $this->storage->listDirectories($userId);

        return in_array($directoryPath, $directories, true);
    }
    public function delete(int $userId, string $filePath): void
    {
    $user = $this->getUser($userId);
    $filePath = $this->normalizePath($filePath);

    if ($filePath === '') {
        throw new \InvalidArgumentException('File path is empty');
    }

    $fileEntry = $this->entityManager
        ->getRepository(FileEntry::class)
        ->findOneBy([
            'user' => $user,
            'path' => $filePath,
        ]);

    if ($fileEntry === null) {
        throw new \RuntimeException('File not found');
    }

    $blob = $fileEntry->getBlob();

    $this->entityManager->remove($fileEntry);

    $blob->decrementRefCount();

    if ($blob->getRefCount() === 0) {
        $this->storage->deleteBlob($blob->getStorageKey());
        $this->entityManager->remove($blob);
    }

    $this->entityManager->flush();
    }
    
}
