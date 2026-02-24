<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Service\StatsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class StatsController
{
    public function __construct(private StatsService $statsService)
    {
    }

    public function getStats(Request $request): ResponseInterface
    {
        $peakHours = $this->statsService->getPeakOccupancyHours();
        return JsonResponse::ok([
            'peak_occupancy_hours' => array_map(
                fn(array $row) => [
                    'hour' => (int) $row['hour'],
                    'occupancy' => (int) $row['occupancy'],
                ],
                $peakHours
            ),
        ]);
    }
}
