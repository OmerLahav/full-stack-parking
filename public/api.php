<?php

declare(strict_types=1);

/**
 * Smart Parking REST API Entry Point
 */

use App\Config\Config;
use App\Repository\ParkingSpotRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\PubSub\RedisPubSub;
use App\Service\ReservationService;
use App\Middleware\JwtAuthMiddleware;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

$baseDir = dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

// Load .env
$envFile = $baseDir . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

// Ensure DB connection is initialized (loads Config)
\App\Database\Database::getConnection();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS');
});

// OPTIONS preflight
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Dependencies
$userRepo = new UserRepository();
$spotRepo = new ParkingSpotRepository();
$reservationRepo = new ReservationRepository();
$authService = new AuthService($userRepo);
$pubSub = new RedisPubSub();
$reservationService = new ReservationService($reservationRepo, $spotRepo, $pubSub);
$jwtAuth = new JwtAuthMiddleware($authService);

// Public routes
$app->post('/login', function ($request, $response) use ($authService) {
    $body = $request->getParsedBody() ?? [];
    $email = $body['email'] ?? $body['username'] ?? '';
    $password = $body['password'] ?? '';

    if (empty($email) || empty($password)) {
        $response->getBody()->write(json_encode(['error' => 'Email and password are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $user = $authService->authenticate($email, $password);
    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $token = $authService->createToken($user);
    $response->getBody()->write(json_encode([
        'token' => $token,
        'user' => ['id' => $user['id'], 'email' => $user['email']],
    ]));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

// Protected routes
$app->group('', function (RouteCollectorProxy $group) use (
    $reservationService,
    $spotRepo,
    $reservationRepo
) {
    $group->get('/spots', function ($request, $response) use ($reservationService) {
        $spots = $reservationService->getSpots();
        $response->getBody()->write(json_encode(['spots' => $spots]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    $group->get('/reservations', function ($request, $response) use ($reservationService) {
        $date = $request->getQueryParams()['date'] ?? date('Y-m-d');
        $reservations = $reservationService->getReservationsForDate($date);
        $response->getBody()->write(json_encode(['reservations' => $reservations]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });

    $group->post('/reservations', function ($request, $response) use ($reservationService) {
        $user = $request->getAttribute('user');
        $body = $request->getParsedBody() ?? [];

        $spotId = (int) ($body['spot_id'] ?? 0);
        $startTime = $body['start_time'] ?? '';
        $endTime = $body['end_time'] ?? '';

        if (!$spotId || !$startTime || !$endTime) {
            $response->getBody()->write(json_encode([
                'error' => 'spot_id, start_time, and end_time are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $reservation = $reservationService->create($user['id'], $spotId, $startTime, $endTime);
            $response->getBody()->write(json_encode($reservation));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
    });

    $group->put('/reservations/{id}/complete', function ($request, $response, $args) use ($reservationService) {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);

        if (!$id) {
            $response->getBody()->write(json_encode(['error' => 'Invalid reservation ID']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $reservation = $reservationService->complete($id, $user['id']);
            $response->getBody()->write(json_encode($reservation));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    });
})->add($jwtAuth);

$app->run();
