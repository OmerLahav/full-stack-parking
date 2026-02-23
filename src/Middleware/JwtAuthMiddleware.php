<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuthService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

class JwtAuthMiddleware
{
    public function __construct(private AuthService $authService)
    {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->jsonResponse(401, ['error' => 'Missing or invalid Authorization header']);
        }

        $token = $matches[1];
        $user = $this->authService->validateToken($token);
        if (!$user) {
            return $this->jsonResponse(401, ['error' => 'Invalid or expired token']);
        }

        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }

    private function jsonResponse(int $status, array $data): Response
    {
        $response = new Response();
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
