<?php

namespace App\Middleware;

use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class RequestLoggingMiddleware implements MiddlewareInterface
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        $start = microtime(true);

        $this->logger->info('Request started', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ]);

        try {
            $response = $handler->handle($request);

            $this->logger->info('Request finished', [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Request failed', [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }
}