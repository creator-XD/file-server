<?php

namespace App\Storage;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Psr\Http\Message\UploadedFileInterface;

final class S3Storage implements StorageInterface
{
    private S3Client $client;
    private string $bucket;

    public function __construct(
        string $endpoint,
        string $region,
        string $bucket,
        string $accessKey,
        string $secretKey,
        bool $usePathStyleEndpoint = true
    ) {
        $this->bucket = $bucket;

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $usePathStyleEndpoint,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
        ]);
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

    private function getUserPrefix(int $userId): string
    {
        return $userId . '/';
    }

    private function getObjectKey(int $userId, string $relativePath): string
    {
        $relativePath = $this->normalizePath($relativePath);

        if ($relativePath === '') {
            return $this->getUserPrefix($userId);
        }

        return $this->getUserPrefix($userId) . $relativePath;
    }

    private function objectExists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }

            throw $e;
        }
    }

    private function directoryExists(int $userId, string $directoryPath): bool
    {
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            return true;
        }

        $prefix = $this->getObjectKey($userId, $directoryPath) . '/';

        $result = $this->client->listObjectsV2([
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
            'MaxKeys' => 1,
        ]);

        return isset($result['Contents']) && count($result['Contents']) > 0;
    }

    public function listDirectories(int $userId): array
    {
        $prefix = $this->getUserPrefix($userId);
        $directories = [];
        $continuationToken = null;

        do {
            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ];

            if ($continuationToken !== null) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $this->client->listObjectsV2($params);

            foreach ($result['Contents'] ?? [] as $object) {
                $key = $object['Key'];

                if (!str_starts_with($key, $prefix)) {
                    continue;
                }

                $relativePath = substr($key, strlen($prefix));
                $relativePath = trim($relativePath, '/');

                if ($relativePath === '') {
                    continue;
                }

                $parts = explode('/', $relativePath);

                array_pop($parts);

                $current = [];

                foreach ($parts as $part) {
                    $current[] = $part;
                    $directories[] = implode('/', $current);
                }
            }

            $continuationToken = $result['NextContinuationToken'] ?? null;
        } while ($continuationToken !== null);

        $directories = array_values(array_unique($directories));
        sort($directories);

        return $directories;
    }

    public function createDirectory(int $userId, string $directoryPath): void
    {
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            throw new \InvalidArgumentException('Directory path is empty');
        }

        if ($this->directoryExists($userId, $directoryPath)) {
            throw new \RuntimeException('Directory already exists');
        }

        $key = $this->getObjectKey($userId, $directoryPath) . '/.dir';

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => '',
        ]);
    }

    public function deleteDirectory(int $userId, string $directoryPath): void
    {
        $directoryPath = $this->normalizePath($directoryPath);

        if ($directoryPath === '') {
            throw new \InvalidArgumentException('Cannot delete root directory');
        }

        $prefix = $this->getObjectKey($userId, $directoryPath) . '/';

        $objectsToDelete = [];
        $continuationToken = null;

        do {
            $params = [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ];

            if ($continuationToken !== null) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $this->client->listObjectsV2($params);

            foreach ($result['Contents'] ?? [] as $object) {
                $objectsToDelete[] = [
                    'Key' => $object['Key'],
                ];
            }

            $continuationToken = $result['NextContinuationToken'] ?? null;
        } while ($continuationToken !== null);

        if (count($objectsToDelete) === 0) {
            throw new \RuntimeException('Directory not found');
        }

        foreach (array_chunk($objectsToDelete, 1000) as $chunk) {
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $chunk,
                ],
            ]);
        }
    }

    public function uploadFile(int $userId, string $directoryPath, UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $directoryPath = $this->normalizePath($directoryPath);

        if (!$this->directoryExists($userId, $directoryPath)) {
            throw new \RuntimeException('Directory does not exist');
        }

        $fileName = $file->getClientFilename();

        if ($fileName === null || $fileName === '') {
            throw new \RuntimeException('File name is empty');
        }

        $this->validateFileName($fileName);

        $key = $this->getObjectKey(
            $userId,
            $directoryPath === '' ? $fileName : $directoryPath . '/' . $fileName
        );

        if ($this->objectExists($key)) {
            throw new \RuntimeException('File already exists');
        }

        $stream = $file->getStream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $stream->getContents(),
        ]);

        return $fileName;
    }

    public function readFile(int $userId, string $filePath): string
    {
        $key = $this->getObjectKey($userId, $filePath);

        if (!$this->objectExists($key)) {
            throw new \RuntimeException('File not found');
        }

        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        return $result['Body']->getContents();
    }

    public function renameFile(int $userId, string $oldPath, string $newName): void
    {
        $oldKey = $this->getObjectKey($userId, $oldPath);

        if (!$this->objectExists($oldKey)) {
            throw new \RuntimeException('File not found');
        }

        $this->validateFileName($newName);

        $oldPath = $this->normalizePath($oldPath);
        $directory = dirname($oldPath);

        if ($directory === '.' || $directory === '') {
            $newRelativePath = $newName;
        } else {
            $newRelativePath = $directory . '/' . $newName;
        }

        $newKey = $this->getObjectKey($userId, $newRelativePath);

        if ($this->objectExists($newKey)) {
            throw new \RuntimeException('File with this name already exists');
        }

        $this->client->copyObject([
            'Bucket' => $this->bucket,
            'Key' => $newKey,
            'CopySource' => $this->bucket . '/' . $oldKey,
        ]);

        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $oldKey,
        ]);
    }

    public function replaceFile(int $userId, string $filePath, UploadedFileInterface $newFile): void
    {
        if ($newFile->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error');
        }

        $key = $this->getObjectKey($userId, $filePath);

        if (!$this->objectExists($key)) {
            throw new \RuntimeException('File not found');
        }

        $stream = $newFile->getStream();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $stream->getContents(),
        ]);
    }
}