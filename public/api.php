<?php

declare(strict_types=1);

/**
 * Smart Parking REST API Entry Point
 */

use App\Controller\AuthController;
use App\Controller\ReservationController;
use App\Controller\StatsController;
use App\Middleware\JwtAuthMiddleware;
use App\Repository\ParkingSpotRepository;
use App\Repository\ReservationRepository;
use App\Repository\StatsRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\PubSub\RedisPubSub;
use App\Service\ReservationService;
use App\Service\StatsService;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

$baseDir = dirname(__DIR__);
require $baseDir . '/vendor/autoload.php';

// Load .env (Config loads on first use; ensure env is available for other scripts)
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

// Ensure DB connection is initialized
\App\Database\Database::getConnection();

// Dependencies
$userRepo = new UserRepository();
$spotRepo = new ParkingSpotRepository();
$reservationRepo = new ReservationRepository();
$authService = new AuthService($userRepo);
$pubSub = new RedisPubSub();
$reservationService = new ReservationService($reservationRepo, $spotRepo, $pubSub);
$jwtAuth = new JwtAuthMiddleware($authService);

$authController = new AuthController($authService);
$reservationController = new ReservationController($reservationService);
$statsController = new StatsController(new StatsService(new StatsRepository()));

// App setup
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

$app->options('/{routes:.+}', fn($req, $res, $args) => $res);

// Routes
$app->post('/login', fn($req, $res, $args) => $authController->login($req));

$app->group('', function (RouteCollectorProxy $group) use ($reservationController, $statsController) {
    $group->get('/spots', fn($req, $res, $args) => $reservationController->getSpots($req));
    $group->get('/reservations', fn($req, $res, $args) => $reservationController->getReservations($req));
    $group->get('/stats', fn($req, $res, $args) => $statsController->getStats($req));
    $group->post('/reservations', fn($req, $res, $args) => $reservationController->createReservation($req));
    $group->put('/reservations/{id}/complete', function ($req, $res, $args) use ($reservationController) {
        $id = (int) ($args['id'] ?? 0);
        return $reservationController->completeReservation($req, $id);
    });
})->add($jwtAuth);

$app->run();
