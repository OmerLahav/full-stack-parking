<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Service\ReservationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReservationController
{
    public function __construct(private ReservationService $reservationService)
    {
    }

    public function getSpots(Request $request): ResponseInterface
    {
        $spots = $this->reservationService->getSpots();
        return JsonResponse::ok(['spots' => $spots]);
    }

    public function getReservations(Request $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $date = $params['date'] ?? date('Y-m-d');
        $reservations = $this->reservationService->getReservationsForDate($date);
        return JsonResponse::ok(['reservations' => $reservations]);
    }

    public function createReservation(Request $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = $request->getParsedBody() ?? [];

        $spotId = (int) ($body['spot_id'] ?? 0);
        $startTime = $body['start_time'] ?? '';
        $endTime = $body['end_time'] ?? '';

        if (!$spotId || !$startTime || !$endTime) {
            return JsonResponse::error('spot_id, start_time, and end_time are required', 400);
        }

        try {
            $reservation = $this->reservationService->create($user['id'], $spotId, $startTime, $endTime);
            return JsonResponse::ok($reservation, 201);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return JsonResponse::error($e->getMessage(), 409);
        }
    }

    public function completeReservation(Request $request, int $id): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$id) {
            return JsonResponse::error('Invalid reservation ID', 400);
        }

        try {
            $reservation = $this->reservationService->complete($id, $user['id']);
            return JsonResponse::ok($reservation);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }
}
