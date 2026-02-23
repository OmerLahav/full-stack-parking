<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

/**
 * Helper for consistent JSON API responses.
 */
final class JsonResponse
{
    public static function ok(array $data, int $status = 200): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public static function error(string $message, int $status = 400): ResponseInterface
    {
        return self::ok(['error' => $message], $status);
    }
}
