<?php

use Slim\Factory\AppFactory;
use App\Auth\LoginService;
use App\Middleware\AuthMiddleware;
use App\Service\DirectoryService;
use App\Service\FileService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$entityManager = require __DIR__ . '/../src/Config/doctrine.php';

$app = AppFactory::create();

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

$app->post('/login', function (Request $request, Response $response) use ($entityManager) {
    $data = json_decode($request->getBody()->getContents(), true);

    $login = $data['login'] ?? '';
    $password = $data['password'] ?? '';

    $service = new LoginService($entityManager);

    $token = $service->login($login, $password);

    if (!$token) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid credentials'
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

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

$app->get('/directories', function ($request, $response) {

    $user = $request->getAttribute('user');

    $service = new DirectoryService();

    $dirs = $service->list($user->getId());

    $response->getBody()->write(json_encode($dirs));

    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware($entityManager));
$app->post('/directories', function ($request, $response) {

    $user = $request->getAttribute('user');

    $data = json_decode($request->getBody()->getContents(), true);

    $name = $data['name'] ?? '';

    $service = new DirectoryService();

    $result = $service->create($user->getId(), $name);

    $response->getBody()->write(json_encode([
        'success' => $result
    ]));

    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware($entityManager));

$app->delete('/directories', function ($request, $response) {

    $user = $request->getAttribute('user');

    $data = json_decode($request->getBody()->getContents(), true);

    $name = $data['name'] ?? '';

    $service = new DirectoryService();

    $result = $service->delete($user->getId(), $name);

    $response->getBody()->write(json_encode([
        'success' => $result
    ]));

    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware($entityManager));

$app->post('/files/upload', function (Request $request, Response $response) {
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
        $service = new FileService();

        $fileName = $service->upload(
            $user->getId(),
            $directory,
            $uploadedFiles['file']
        );

        $response->getBody()->write(json_encode([
            'message' => 'File uploaded',
            'file' => $fileName
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);

    } catch (Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->get('/files/download', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');

    $params = $request->getQueryParams();
    $path = $params['path'] ?? '';

    try {
        $service = new FileService();
        $filePath = $service->download($user->getId(), $path);

        $fileName = basename($filePath);

        $response->getBody()->write(file_get_contents($filePath));

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->withStatus(200);

    } catch (Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->put('/files/rename', function (Request $request, Response $response) {
    $user = $request->getAttribute('user');

    $data = json_decode($request->getBody()->getContents(), true);

    $oldPath = $data['old_path'] ?? '';
    $newName = $data['new_name'] ?? '';

    try {
        $service = new FileService();

        $service->rename($user->getId(), $oldPath, $newName);

        $response->getBody()->write(json_encode([
            'message' => 'File renamed'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->post('/files/replace', function (Request $request, Response $response) {
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
        $service = new FileService();

        $service->replace(
            $user->getId(),
            $path,
            $uploadedFiles['file']
        );

        $response->getBody()->write(json_encode([
            'message' => 'File replaced'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

    } catch (Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage()
        ]));

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
})->add(new AuthMiddleware($entityManager));

$app->run();