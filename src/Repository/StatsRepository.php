<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constants\ReservationStatus;
use App\Database\Database;
use PDO;

class StatsRepository
{
    /**
     * Returns occupancy count per hour (0-23), ordered by count descending.
     * Counts reservations (Booked + Completed) that overlap each hour.
     */
    public function getOccupancyByHour(): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT h.hour, COUNT(r.id) AS occupancy
            FROM (
                SELECT 0 AS hour UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
                UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23
            ) h
            LEFT JOIN reservations r ON r.status IN (?, ?)
                AND TIME(r.start_time) < ADDTIME(MAKETIME(h.hour, 0, 0), '1:00:00')
                AND TIME(r.end_time) > MAKETIME(h.hour, 0, 0)
            GROUP BY h.hour
            ORDER BY occupancy DESC, h.hour ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([ReservationStatus::BOOKED, ReservationStatus::COMPLETED]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
