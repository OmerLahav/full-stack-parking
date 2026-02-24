<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repository\UserRepository;

class PasswordAuthStrategy implements AuthStrategyInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function authenticate(array $credentials): ?array
    {
        $email = $credentials['email'] ?? $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        if (empty($email) || empty($password)) {
            return null;
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
        ];
    }
}
