<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponse;
use App\Service\AuthService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface;

class JwtAuthMiddleware
{
    public function __construct(private AuthService $authService)
    {
    }

    public function __invoke(Request $request, RequestHandler $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return JsonResponse::error('Missing or invalid Authorization header', 401);
        }

        $token = $matches[1];
        $user = $this->authService->validateToken($token);
        if (!$user) {
            return JsonResponse::error('Invalid or expired token', 401);
        }

        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }
}
