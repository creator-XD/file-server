<?php

namespace Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Интеграция API')]
final class ApiIntegrationTest extends TestCase
{
    private string $baseUrl = 'http://127.0.0.1:8000';
    private string $token;
    private string $directory;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->directory = 'it_' . uniqid();

        $this->entityManager = require __DIR__ . '/../../src/Config/doctrine.php';

        $this->token = $this->loginAndGetToken();
    }

    #[TestDox('Полный сценарий работы с файлами и дедупликацией')]
    public function testFullFileApiFlowWithDeduplication(): void
    {
        $usageBefore = $this->getUsage();

        $this->assertSame('free', $usageBefore['plan']);

        $this->createDirectory($this->directory);

        $content = 'Same integration test content ' . uniqid();

        $firstFileName = 'file_one.txt';
        $secondFileName = 'file_two.txt';

        $this->uploadFile($this->directory, $firstFileName, $content);
        $this->uploadFile($this->directory, $secondFileName, $content);

        $downloadedContent = $this->downloadFile(
            $this->directory . '/' . $firstFileName
        );

        $this->assertSame($content, $downloadedContent);

        $files = $this->fetchAllAssociative(
            "SELECT path, blob_id FROM files WHERE path IN (?, ?) ORDER BY path",
            [
                $this->directory . '/' . $firstFileName,
                $this->directory . '/' . $secondFileName,
            ]
        );

        $this->assertCount(2, $files);
        $this->assertSame($files[0]['blob_id'], $files[1]['blob_id']);

        $blobId = $files[0]['blob_id'];

        $blobs = $this->fetchAllAssociative(
            "SELECT id, ref_count FROM file_blobs WHERE id = ?",
            [$blobId]
        );

        $this->assertCount(1, $blobs);
        $this->assertSame(2, (int) $blobs[0]['ref_count']);

        $this->deleteFile($this->directory . '/' . $firstFileName);

        $blobsAfterOneDelete = $this->fetchAllAssociative(
            "SELECT id, ref_count FROM file_blobs WHERE id = ?",
            [$blobId]
        );

        $this->assertCount(1, $blobsAfterOneDelete);
        $this->assertSame(1, (int) $blobsAfterOneDelete[0]['ref_count']);

        $this->deleteDirectory($this->directory);

        $remainingFiles = $this->fetchAllAssociative(
            "SELECT id FROM files WHERE path LIKE ?",
            [$this->directory . '/%']
        );

        $this->assertCount(0, $remainingFiles);

        $remainingBlobs = $this->fetchAllAssociative(
            "SELECT id FROM file_blobs WHERE id = ?",
            [$blobId]
        );

        $this->assertCount(0, $remainingBlobs);
        $usageAfterCleanup = $this->getUsage();

        $this->assertSame(
            $usageBefore['used_bytes'],
            $usageAfterCleanup['used_bytes']
        );
    }

    #[TestDox('Успешная замена файла')]
    public function testSuccessfulFileReplace(): void
    {
        $this->createDirectory($this->directory);

        $path = $this->directory . '/replace.txt';

        $oldContent = 'old content';
        $newContent = 'new content';

        $this->uploadFile(
            $this->directory,
            'replace.txt',
            $oldContent
        );

        $response = $this->replaceFile(
            $path,
            'replace.txt',
            $newContent
        );

        $this->assertSame(
            200,
            $response['status'],
            $response['body']
        );

        $downloadedContent = $this->downloadFile($path);

        $this->assertSame(
            $newContent,
            $downloadedContent
        );

        $this->deleteDirectory($this->directory);
    }

    #[TestDox('Вход с неверным паролем отклоняется')]
    public function testLoginWithWrongPasswordIsRejected(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/login',
            [
                'login' => 'test_api',
                'password' => 'wrong_password',
            ]
        );

        $this->assertContains(
            $response['status'],
            [400, 401],
            $response['body']
        );

        $this->assertArrayHasKey(
            'error',
            $response['json']
        );
    }

    #[TestDox('Защищённый endpoint без токена отклоняется')]
    public function testProtectedEndpointWithoutTokenIsRejected(): void
    {
        $response = $this->rawRequest(
            'GET',
            '/directories',
            null,
            false
        );

        $this->assertContains(
            $response['status'],
            [401, 403],
            $response['body']
        );
    }

    #[TestDox('Замена файла сверх лимита плана отклоняется')]
    public function testReplaceOverPlanLimitIsRejected(): void
    {
        $this->createDirectory($this->directory);
        $usageFillerBlobId = null;

        $path = $this->directory . '/small.txt';

        $oldContent = 'original content';

        $this->uploadFile(
            $this->directory,
            'small.txt',
            $oldContent
        );

        $usage = $this->getUsage();
        $usageFillerSize = max(
            0,
            $usage['storage_limit_bytes'] - $usage['used_bytes'] - (1024 * 1024)
        );

        if ($usageFillerSize > 0) {
            $usageFillerBlobId = $this->createUsageFiller(
                $this->getTestUserId(),
                $usageFillerSize
            );
        }

        $overLimitContent = str_repeat(
            'A',
            2 * 1024 * 1024
        );

        try {
            $response = $this->replaceFile(
                $path,
                'large.txt',
                $overLimitContent
            );

            $this->assertSame(
                400,
                $response['status'],
                $response['body']
            );

            $downloadedContent = $this->downloadFile($path);

            $this->assertSame(
                $oldContent,
                $downloadedContent
            );
        } finally {
            if ($usageFillerBlobId !== null) {
                $this->deleteUsageFiller($usageFillerBlobId);
            }

            $this->deleteDirectory($this->directory);
        }
    }

    #[TestDox('Загрузка файла сверх лимита плана отклоняется')]
    public function testUploadOverPlanLimitIsRejected(): void
    {
        $this->createDirectory($this->directory);
        $usageFillerBlobId = null;

        $usage = $this->getUsage();
        $usageFillerSize = max(
            0,
            $usage['storage_limit_bytes'] - $usage['used_bytes'] - (1024 * 1024)
        );

        if ($usageFillerSize > 0) {
            $usageFillerBlobId = $this->createUsageFiller(
                $this->getTestUserId(),
                $usageFillerSize
            );
        }

        $overLimitContent = str_repeat(
            'A',
            2 * 1024 * 1024
        );

        try {
            $response = $this->uploadFileExpectingResponse(
                $this->directory,
                'too_large.txt',
                $overLimitContent
            );

            $this->assertSame(
                400,
                $response['status'],
                $response['body']
            );

            $files = $this->fetchAllAssociative(
                'SELECT id FROM files WHERE path = ?',
                [
                    $this->directory . '/too_large.txt',
                ]
            );

            $this->assertCount(0, $files);
        } finally {
            if ($usageFillerBlobId !== null) {
                $this->deleteUsageFiller($usageFillerBlobId);
            }

            $this->deleteDirectory($this->directory);
        }
    }

    private function loginAndGetToken(): string
    {
        $response = $this->jsonRequest('POST', '/login', [
            'login' => 'test_api',
            'password' => '123456',
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertArrayHasKey('token', $response['json']);

        return $response['json']['token'];
    }

    private function createDirectory(string $directory): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/directories',
            [
                'name' => $directory,
            ],
            true
        );

        $this->assertContains(
            $response['status'],
            [200, 201],
            'Create directory failed: ' . $response['body']
        );
    }

    private function deleteDirectory(string $directory): void
    {
        $response = $this->rawRequest(
            'DELETE',
            '/directories?path=' . urlencode($directory),
            null,
            true
        );

        $this->assertContains($response['status'], [200, 204]);
    }

    private function uploadFile(string $directory, string $fileName, string $content): void
    {
        $response = $this->uploadFileExpectingResponse(
            $directory,
            $fileName,
            $content
        );

        $this->assertContains($response['status'], [200, 201], $response['body']);
    }

    private function uploadFileExpectingResponse(
        string $directory,
        string $fileName,
        string $content
    ): array {
        $boundary = '----PhpUnitBoundary' . uniqid();

        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="directory"' . "\r\n\r\n";
        $body .= $directory . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . "\r\n";
        $body .= 'Content-Type: text/plain' . "\r\n\r\n";
        $body .= $content . "\r\n";

        $body .= '--' . $boundary . "--\r\n";

        return $this->rawRequest(
            'POST',
            '/files/upload',
            $body,
            true,
            [
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ]
        );
    }

    private function replaceFile(
        string $path,
        string $fileName,
        string $content
    ): array {
        $boundary = '----PhpUnitBoundary' . uniqid();

        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="path"' . "\r\n\r\n";
        $body .= $path . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $fileName . '"' . "\r\n";
        $body .= 'Content-Type: text/plain' . "\r\n\r\n";
        $body .= $content . "\r\n";

        $body .= '--' . $boundary . "--\r\n";

        return $this->rawRequest(
            'POST',
            '/files/replace',
            $body,
            true,
            [
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ]
        );
    }

    private function downloadFile(string $path): string
    {
        $response = $this->rawRequest(
            'GET',
            '/files/download?path=' . urlencode($path),
            null,
            true
        );

        $this->assertSame(200, $response['status'], $response['body']);

        return $response['body'];
    }

    private function deleteFile(string $path): void
    {
        $response = $this->rawRequest(
            'DELETE',
            '/files?path=' . urlencode($path),
            null,
            true
        );

        $this->assertContains($response['status'], [200, 204], $response['body']);
    }

    private function jsonRequest(string $method, string $path, array $data, bool $auth = false): array
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);

        $response = $this->rawRequest(
            $method,
            $path,
            $body,
            $auth,
            [
                'Content-Type: application/json',
            ]
        );

        $json = json_decode($response['body'], true);

        $this->assertIsArray($json, 'Response is not valid JSON: ' . $response['body']);

        $response['json'] = $json;

        return $response;
    }

    private function rawRequest(
        string $method,
        string $path,
        ?string $body = null,
        bool $auth = false,
        array $extraHeaders = []
    ): array {
        $headers = $extraHeaders;

        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = file_get_contents($this->baseUrl . $path, false, $context);

        if ($responseBody === false) {
            throw new \RuntimeException('HTTP request failed: ' . $method . ' ' . $path);
        }

        $status = 0;

        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }

        return [
            'status' => $status,
            'body' => $responseBody,
            'headers' => $http_response_header ?? [],
        ];
    }

    private function fetchAllAssociative(string $sql, array $params = []): array
    {
        return $this->entityManager
            ->getConnection()
            ->fetchAllAssociative($sql, $params);
    }

    private function getTestUserId(): int
    {
        $userId = $this->entityManager
            ->getConnection()
            ->fetchOne('SELECT id FROM users WHERE login = ?', ['test_api']);

        $this->assertNotFalse($userId);

        return (int) $userId;
    }

    private function createUsageFiller(int $userId, int $size): int
    {
        $hash = hash('sha256', 'usage-filler-' . uniqid('', true));
        $storageKey = 'test-usage-fillers/' . $hash;
        $connection = $this->entityManager->getConnection();

        $blobId = $connection->fetchOne(
            'INSERT INTO file_blobs (hash, storage_key, size, mime_type, ref_count, created_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
             RETURNING id',
            [
                $hash,
                $storageKey,
                $size,
                'application/octet-stream',
                1,
            ]
        );

        $connection->executeStatement(
            'INSERT INTO files (user_id, blob_id, path, name, size, mime_type, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                $userId,
                $blobId,
                'test-usage-fillers/' . $hash . '.bin',
                $hash . '.bin',
                $size,
                'application/octet-stream',
            ]
        );

        return (int) $blobId;
    }

    private function deleteUsageFiller(int $blobId): void
    {
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('DELETE FROM files WHERE blob_id = ?', [$blobId]);
        $connection->executeStatement('DELETE FROM file_blobs WHERE id = ?', [$blobId]);
    }

    private function getUsage(): array
    {
        $response = $this->rawRequest(
            'GET',
            '/usage',
            null,
            true
        );

        $this->assertSame(
            200,
            $response['status'],
            $response['body']
        );

        $data = json_decode(
            $response['body'],
            true
        );

        $this->assertIsArray(
            $data,
            'Usage response is not valid JSON: ' . $response['body']
        );

        return $data;
    }
}
