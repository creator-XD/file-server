<?php

namespace App\Service;

class DirectoryService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = __DIR__ . '/../../storage';
    }

    public function list(int $userId): array
    {
        $path = $this->basePath . "/$userId";

        if (!is_dir($path)) {
            return [];
        }

        return array_values(array_diff(scandir($path), ['.', '..']));
    }

    public function create(int $userId, string $name): bool
    {
        $path = $this->basePath . "/$userId/$name";

        if (!is_dir($this->basePath . "/$userId")) {
            mkdir($this->basePath . "/$userId", 0777, true);
        }

        return mkdir($path);
    }

    public function delete(int $userId, string $name): bool
    {
        $path = $this->basePath . "/$userId/$name";

        if (!is_dir($path)) {
            return false;
        }

        return rmdir($path);
    }
}