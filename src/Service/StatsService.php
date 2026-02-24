<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\StatsRepository;

class StatsService
{
    public function __construct(private StatsRepository $statsRepository)
    {
    }

    /**
     * Returns peak occupancy hours (hours with highest reservation count).
     * Format: [ { "hour": 10, "occupancy": 15 }, ... ] sorted by occupancy descending.
     */
    public function getPeakOccupancyHours(): array
    {
        return $this->statsRepository->getOccupancyByHour();
    }
}
