<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function login(Request $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $email = $body['email'] ?? $body['username'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            return JsonResponse::error('Email and password are required', 400);
        }

        $user = $this->authService->authenticate($email, $password);
        if (!$user) {
            return JsonResponse::error('Invalid credentials', 401);
        }

        $token = $this->authService->createToken($user);
        return JsonResponse::ok([
            'token' => $token,
            'user' => ['id' => $user['id'], 'email' => $user['email']],
        ], 200);
    }
}
