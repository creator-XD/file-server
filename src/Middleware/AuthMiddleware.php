<?php

namespace App\Middleware;

use App\Entity\Session;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;

class AuthMiddleware
{
    public function __construct(
        private EntityManager $em
    ) {}

    public function __invoke(Request $request, Handler $handler)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            return $this->unauthorized();
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized();
        }

        $token = substr($authHeader, 7);

        $session = $this->em
            ->getRepository(Session::class)
            ->findOneBy(['token' => $token]);

        if (!$session) {
            return $this->unauthorized();
        }

        if ($session->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->unauthorized();
        }

        $request = $request->withAttribute('user', $session->getUser());

        return $handler->handle($request);
    }

    private function unauthorized()
    {
        $response = new Response();

        $response->getBody()->write(json_encode([
            'error' => 'Unauthorized'
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
}