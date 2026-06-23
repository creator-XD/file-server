<?php

namespace App\Service;

use Psr\Http\Message\UploadedFileInterface;

class FileService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = __DIR__ . '/../../storage';
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

    private function getSafePath(int $userId, string $relativePath): string
    {
        $userBasePath = $this->getUserBasePath($userId);
        $normalizedPath = $this->normalizePath($relativePath);

        if ($normalizedPath === '') {
            return $userBasePath;
        }

        return $userBasePath . '/' . $normalizedPath;
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

    public function upload(int $userId, string $directoryPath, UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $directory = $this->getSafePath($userId, $directoryPath);

        if (!is_dir($directory)) {
            throw new \RuntimeException('Directory does not exist');
        }

        $fileName = $file->getClientFilename();

        if (!$fileName) {
            throw new \RuntimeException('File name is empty');
        }

        $this->validateFileName($fileName);

        $targetPath = $directory . '/' . $fileName;

        if (file_exists($targetPath)) {
            throw new \RuntimeException('File already exists');
        }

        $file->moveTo($targetPath);

        return $fileName;
    }

    public function download(int $userId, string $filePath): string
    {
        $path = $this->getSafePath($userId, $filePath);

        if (!is_file($path)) {
            throw new \RuntimeException('File not found');
        }

        return $path;
    }

    public function rename(int $userId, string $oldPath, string $newName): bool
    {
        $oldFullPath = $this->getSafePath($userId, $oldPath);

        if (!is_file($oldFullPath)) {
            throw new \RuntimeException('File not found');
        }

        $this->validateFileName($newName);

        $directory = dirname($oldFullPath);
        $newFullPath = $directory . '/' . $newName;

        if (file_exists($newFullPath)) {
            throw new \RuntimeException('File with new name already exists');
        }

        return rename($oldFullPath, $newFullPath);
    }

    public function replace(int $userId, string $filePath, UploadedFileInterface $newFile): bool
    {
        if ($newFile->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $targetPath = $this->getSafePath($userId, $filePath);

        if (!is_file($targetPath)) {
            throw new \RuntimeException('File not found');
        }

        $newFile->moveTo($targetPath);

        return true;
    }
}