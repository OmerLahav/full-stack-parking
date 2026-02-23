<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use App\Repository\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private const JWT_TTL_SECONDS = 86400; // 24 hours

    public function __construct(private UserRepository $userRepository)
    {
    }

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
        ];
    }

    public function createToken(array $user): string
    {
        $secret = Config::get('JWT_SECRET', 'default-secret-change-me');
        $payload = [
            'sub' => $user['id'],
            'email' => $user['email'],
            'iat' => time(),
            'exp' => time() + self::JWT_TTL_SECONDS,
        ];
        return JWT::encode($payload, $secret, 'HS256');
    }

    public function validateToken(string $token): ?array
    {
        try {
            $secret = Config::get('JWT_SECRET', 'default-secret-change-me');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return [
                'id' => (int) $decoded->sub,
                'email' => $decoded->email,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
