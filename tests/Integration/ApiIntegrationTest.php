<?php

namespace Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

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

    public function testFullFileApiFlowWithDeduplication(): void
    {
        $this->createDirectory($this->directory);

        $content = 'Same integration test content ' . uniqid();

        $firstFileName = 'file_one.txt';
        $secondFileName = 'file_two.txt';

        $this->uploadFile($this->directory, $firstFileName, $content);
        $this->uploadFile($this->directory, $secondFileName, $content);

        $downloadedContent = $this->downloadFile($this->directory . '/' . $firstFileName);

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
        $response = $this->jsonRequest('POST', '/directories', [
            'name' => $directory,
        ], true);

        $this->assertContains($response['status'], [200, 201]);
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

        $response = $this->rawRequest(
            'POST',
            '/files/upload',
            $body,
            true,
            [
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ]
        );

        $this->assertContains($response['status'], [200, 201], $response['body']);
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
}