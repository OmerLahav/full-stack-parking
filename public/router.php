<?php

declare(strict_types=1);

/**
 * PHP built-in server router.
 * Routes API requests to api.php, serves SPA for all other paths.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// API routes - forward to api.php (avoid forwarding GET /login, /slots to API - those are SPA routes)
$apiPaths = ['/login', '/spots', '/stats', '/reservations'];
$isApiPath = in_array($uri, $apiPaths) || preg_match('#^/reservations/\d+/complete$#', $uri);
$isApiRequest = $isApiPath && ($method !== 'GET' || ($uri !== '/login' && $uri !== '/slots'));
if ($isApiRequest) {
    require __DIR__ . '/api.php';
    return true;
}

// Static files - let the server handle them
$file = __DIR__ . $uri;
if ($uri !== '/' && $uri !== '' && file_exists($file) && !is_dir($file)) {
    return false;
}

// SPA fallback - serve index.html for client-side routes
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
return true;
