<?php

use Slim\Factory\AppFactory;
use App\Auth\LoginService;
use App\Middleware\AuthMiddleware;
use App\Middleware\RequestLoggingMiddleware;
use App\Service\DirectoryService;
use App\Service\FileService;
use App\Config\LoggerFactory;
use App\Config\AppConfig;
use App\Storage\StorageFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$entityManager = require __DIR__ . '/../src/Config/doctrine.php';

$config = AppConfig::load();
$storage = StorageFactory::create($config);
$logger = LoggerFactory::create($config);
$app = AppFactory::create();

$app->add(new RequestLoggingMiddleware($logger));

$app->get('/ping', function (Request $request, Response $response) {
    $data = [
        'status' => 'ok'
    ];

    $response->getBody()->write(json_encode($data));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->get('/favicon.ico', function (Request $request, Response $response) {
    return $response->withStatus(204);
});

$app->post('/login', function (Request $request, Response $response) use ($entityManager, $logger) {
    $data = json_decode($request->getBody()->getContents(), true);

    $login = $data['login'] ?? '';
    $password = $data['password'] ?? '';

    $service = new LoginService($entityManager);

    try {
        $token = $service->login($login, $password);
    } catch (Throwable $e) {
        $logger->warning('Login failed', [
            'login' => $login ?? null,
            'error' => $e->getMessage(),
        ]);

        throw $e;
    }

    if (!$token) {
        $logger->warning('Login failed', [
            'login' => $login,
            'error' => 'Invalid credentials',
        ]);

        $response->getBody()->write(json_encode([
            'error' => 'Invalid credentials'
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    $logger->info('User logged in', [
        'login' => $login,
    ]);

    $response->getBody()->write(json_encode([
        'token' => $token
    ]));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->get('/protected-test', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');

    $response->getBody()->write(json_encode([
        'message' => 'Access granted',
        'user_id' => $user->getId(),
        'login' => $user->getLogin()
    ]));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
})->add(new AuthMiddleware($entityManager));

$app->get('/directories', function ($request, $response) use ($storage, $entityManager, $logger) {

    $user = $request->getAttribute('user');

    try {
        $service = new DirectoryService($storage, $entityManager);

        $dirs = $service->list($user->getId());
    } catch (Throwable $e) {
        $logger->warning('Directory operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'list',
            'error' => $e->getMessage(),
        ]);

        throw $e;
    }

    $logger->info('Directories listed', [
        'user_id' => $user->getId(),
    ]);

    $response->getBody()->write(json_encode($dirs));

    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware($entityManager));
$app->post('/directories', function ($request, $response) use ($storage, $entityManager, $logger) {

    $user = $request->getAttribute('user');

    $data = json_decode($request->getBody()->getContents(), true);

    if (!is_array($data)) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid JSON'
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    $name = $data['name'] ?? '';

    $service = new DirectoryService($storage, $entityManager);

    try {
        $service->create($user->getId(), $name);
    } catch (InvalidArgumentException $e) {
        $logger->warning('Directory operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'create',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    } catch (RuntimeException $e) {
        $logger->warning('Directory operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'create',
            'error' => $e->getMessage(),
        ]);

        $status = $e->getMessage() === 'Directory already exists' ? 409 : 400;

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    $logger->info('Directory created', [
        'user_id' => $user->getId(),
        'path' => $name,
    ]);

    $response->getBody()->write(json_encode([
        'success' => true
    ]));

    return $response
        ->withStatus(201)
        ->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware($entityManager));

$app->delete('/directories', function (Request $request, Response $response) use ($storage, $entityManager, $logger) {
    $user = $request->getAttribute('user');

    $params = $request->getQueryParams();
    $path = $params['path'] ?? '';

    try {
        $service = new DirectoryService($storage, $entityManager);
        $service->delete($user->getId(), $path);

        $logger->info('Directory deleted', [
            'user_id' => $user->getId(),
            'path' => $path,
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'Directory deleted'
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');

    } catch (\Throwable $e) {
        $logger->warning('Directory operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'delete',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->post('/files/upload', function (Request $request, Response $response) use ($storage, $entityManager, $logger) {
    $user = $request->getAttribute('user');

    $uploadedFiles = $request->getUploadedFiles();
    $data = $request->getParsedBody();

    $directory = $data['directory'] ?? '';

    if (!isset($uploadedFiles['file'])) {
        $response->getBody()->write(json_encode([
            'error' => 'File is required'
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    try {
        $service = new FileService($storage, $entityManager);

        $fileName = $service->upload(
            $user->getId(),
            $directory,
            $uploadedFiles['file']
        );

        $logger->info('File uploaded', [
            'user_id' => $user->getId(),
            'directory' => $directory,
            'file' => $fileName,
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'File uploaded',
            'file' => $fileName
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);

    } catch (Throwable $e) {
        $logger->warning('File operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'upload',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->get('/files/download', function (Request $request, Response $response) use ($storage, $entityManager, $logger) {
    $user = $request->getAttribute('user');

    $params = $request->getQueryParams();
    $path = $params['path'] ?? '';

    try {
        $service = new FileService($storage, $entityManager);

        $fileContent = $service->download($user->getId(), $path);
        $fileName = basename($path);

        $logger->info('File downloaded', [
            'user_id' => $user->getId(),
            'path' => $path,
        ]);

        $response->getBody()->write($fileContent);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->withStatus(200);

    } catch (Throwable $e) {
        $logger->warning('File operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'download',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->put('/files/rename', function (Request $request, Response $response) use ($storage, $entityManager, $logger) {
    $user = $request->getAttribute('user');

    $data = json_decode($request->getBody()->getContents(), true);

    $oldPath = $data['old_path'] ?? '';
    $newName = $data['new_name'] ?? '';

    try {
        $service = new FileService($storage, $entityManager);

        $service->rename($user->getId(), $oldPath, $newName);

        $logger->info('File renamed', [
            'user_id' => $user->getId(),
            'old_path' => $oldPath,
            'new_name' => $newName,
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'File renamed'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Throwable $e) {
        $logger->warning('File operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'rename',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->post('/files/replace', function (Request $request, Response $response) use ($storage, $entityManager, $logger) {
    $user = $request->getAttribute('user');

    $uploadedFiles = $request->getUploadedFiles();
    $data = $request->getParsedBody();

    $path = $data['path'] ?? '';

    if (!isset($uploadedFiles['file'])) {
        $response->getBody()->write(json_encode([
            'error' => 'File is required'
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }

    try {
        $service = new FileService($storage, $entityManager);

        $service->replace(
            $user->getId(),
            $path,
            $uploadedFiles['file']
        );

        $logger->info('File replaced', [
            'user_id' => $user->getId(),
            'path' => $path,
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'File replaced'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Throwable $e) {
        $logger->warning('File operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'replace',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));
$app->delete('/files', function (Request $request, Response $response) use ($storage, $entityManager, $logger) {
    $user = $request->getAttribute('user');

    $params = $request->getQueryParams();
    $path = $params['path'] ?? '';

    try {
        $service = new FileService($storage, $entityManager);
        $service->delete($user->getId(), $path);

        $logger->info('File deleted', [
            'user_id' => $user->getId(),
            'path' => $path,
        ]);

        $response->getBody()->write(json_encode([
            'message' => 'File deleted'
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json');

    } catch (\Throwable $e) {
        $logger->warning('File operation failed', [
            'user_id' => isset($user) ? $user->getId() : null,
            'operation' => 'delete',
            'error' => $e->getMessage(),
        ]);

        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->run();
