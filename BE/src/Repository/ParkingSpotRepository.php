<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use PDO;

class ParkingSpotRepository
{
    public function findAll(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT id, spot_number, floor_number, type FROM parking_spots ORDER BY spot_number');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, spot_number, floor_number, type FROM parking_spots WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
