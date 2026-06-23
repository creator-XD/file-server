<?php

use Slim\Factory\AppFactory;
use App\Auth\LoginService;
use App\Middleware\AuthMiddleware;
use App\Service\DirectoryService;
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


$app->run();