<?php

namespace App\Service;

class DirectoryService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = __DIR__ . '/../../storage';
    }

    private function getUserBasePath(int $userId): string
    {
        return $this->basePath . "/$userId";
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');

        if ($path === '') {
            throw new \InvalidArgumentException('Directory name is required');
        }

        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new \InvalidArgumentException('Invalid directory path');
            }
        }

        return implode('/', $parts);
    }

    private function getSafePath(int $userId, string $relativePath): string
    {
        return $this->getUserBasePath($userId) . '/' . $this->normalizePath($relativePath);
    }

    public function list(int $userId): array
    {
        $path = $this->getUserBasePath($userId);

        if (!is_dir($path)) {
            return [];
        }

        return array_values(array_filter(
            array_diff(scandir($path), ['.', '..']),
            fn (string $name): bool => is_dir($path . '/' . $name)
        ));
    }

    public function create(int $userId, string $name): bool
    {
        $userBasePath = $this->getUserBasePath($userId);
        $path = $this->getSafePath($userId, $name);

        if (!is_dir($userBasePath) && !mkdir($userBasePath, 0755, true)) {
            throw new \RuntimeException('Unable to create user directory');
        }

        if (file_exists($path)) {
            throw new \RuntimeException('Directory already exists');
        }

        if (!mkdir($path, 0755, true)) {
            throw new \RuntimeException('Unable to create directory');
        }

        return true;
    }

    public function delete(int $userId, string $name): bool
    {
        $path = $this->getSafePath($userId, $name);

        if (!is_dir($path)) {
            throw new \RuntimeException('Directory not found');
        }

        if (!rmdir($path)) {
            throw new \RuntimeException('Unable to delete directory');
        }

        return true;
    }
}
