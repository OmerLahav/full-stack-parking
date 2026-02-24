<?php

declare(strict_types=1);

namespace App\Service;

use App\Auth\AuthStrategyInterface;
use App\Auth\PasswordAuthStrategy;
use App\Config\Config;
use App\Repository\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private const JWT_TTL_SECONDS = 86400; // 24 hours

    public function __construct(
        private UserRepository $userRepository,
        private ?AuthStrategyInterface $passwordStrategy = null,
    ) {
        $this->passwordStrategy ??= new PasswordAuthStrategy($userRepository);
    }

    /**
     * Authenticate with email and password. To add OIDC later, add authenticateFromOidc()
     * and a new strategy; createToken/validateToken stay unchanged.
     */
    public function authenticate(string $email, string $password): ?array
    {
        return $this->passwordStrategy->authenticate(['email' => $email, 'password' => $password]);
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
