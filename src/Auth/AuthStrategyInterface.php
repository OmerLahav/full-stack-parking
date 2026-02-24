<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Strategy for authentication methods (password, OIDC, etc.).
 * Add new implementations to support additional providers without changing AuthService core.
 */
interface AuthStrategyInterface
{
    /**
     * Attempt authentication. Returns user array ['id' => int, 'email' => string] or null.
     */
    public function authenticate(array $credentials): ?array;
}
