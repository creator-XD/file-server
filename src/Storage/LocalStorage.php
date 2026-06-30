<?php

namespace App\Storage;

use Psr\Http\Message\UploadedFileInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LocalStorage implements StorageInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    private function getUserBasePath(int $userId): string
    {
        $userBasePath = $this->basePath . '/' . $userId;

        if (!is_dir($userBasePath)) {
            mkdir($userBasePath, 0777, true);
        }

        return $userBasePath;
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

    private function getFullPath(int $userId, string $relativePath): string
    {
        $userBasePath = $this->getUserBasePath($userId);
        $normalizedPath = $this->normalizePath($relativePath);

        if ($normalizedPath === '') {
            return $userBasePath;
        }

        return $userBasePath . '/' . $normalizedPath;
    }

    public function listDirectories(int $userId): array
    {
        $userBasePath = $this->getUserBasePath($userId);

        $directories = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($userBasePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $relativePath = str_replace($userBasePath . '/', '', $item->getPathname());
                $directories[] = str_replace('\\', '/', $relativePath);
            }
        }

        sort($directories);

        return $directories;
    }

    public function createDirectory(int $userId, string $directoryPath): void
    {
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            throw new \InvalidArgumentException('Directory path is empty');
        }

        $fullPath = $this->getFullPath($userId, $directoryPath);

        if (file_exists($fullPath)) {
            throw new \RuntimeException('Directory already exists');
        }

        mkdir($fullPath, 0777, true);
    }

    public function deleteDirectory(int $userId, string $directoryPath): void
    {
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            throw new \InvalidArgumentException('Cannot delete root directory');
        }

        $fullPath = $this->getFullPath($userId, $directoryPath);

        if (!is_dir($fullPath)) {
            throw new \RuntimeException('Directory not found');
        }

        $this->deleteDirectoryRecursive($fullPath);
    }

    private function deleteDirectoryRecursive(string $directory): void
    {
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

    public function uploadFile(int $userId, string $directoryPath, UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $directoryFullPath = $this->getFullPath($userId, $directoryPath);

        if (!is_dir($directoryFullPath)) {
            throw new \RuntimeException('Directory does not exist');
        }

        $fileName = $file->getClientFilename();

        if ($fileName === null || $fileName === '') {
            throw new \RuntimeException('File name is empty');
        }

        $this->validateFileName($fileName);

        $targetPath = $directoryFullPath . '/' . $fileName;

        if (file_exists($targetPath)) {
            throw new \RuntimeException('File already exists');
        }

        $file->moveTo($targetPath);
        return $fileName;
    }

    public function readFile(int $userId, string $filePath): string
    {
        $fullPath = $this->getFullPath($userId, $filePath);

        if (!is_file($fullPath)) {
            throw new \RuntimeException('File not found');
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new \RuntimeException('Cannot read file');
        }

        return $content;
    }

    public function renameFile(int $userId, string $oldPath, string $newName): void
    {
        $oldFullPath = $this->getFullPath($userId, $oldPath);

        if (!is_file($oldFullPath)) {
            throw new \RuntimeException('File not found');
        }

        $this->validateFileName($newName);

        $directory = dirname($oldFullPath);
        $newFullPath = $directory . '/' . $newName;

        if (file_exists($newFullPath)) {
            throw new \RuntimeException('File with this name already exists');
        }

        rename($oldFullPath, $newFullPath);
    }

    public function replaceFile(int $userId, string $filePath, UploadedFileInterface $newFile): void
    {
        if ($newFile->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $targetPath = $this->getFullPath($userId, $filePath);

        if (!is_file($targetPath)) {
            throw new \RuntimeException('File not found');
        }

        $newFile->moveTo($targetPath);
    }
}