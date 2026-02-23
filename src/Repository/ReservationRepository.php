<?php

declare(strict_types=1);

namespace App\Repository;

use App\Constants\ReservationStatus;
use App\Database\Database;
use PDO;

class ReservationRepository
{
    /**
     * Find overlapping Booked reservations for a spot within the given time range.
     * Uses SELECT FOR UPDATE to lock rows for concurrency safety.
     */
    public function findOverlappingBooked(int $spotId, string $startTime, string $endTime, bool $forUpdate = false): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT id, user_id, spot_id, start_time, end_time, status 
                FROM reservations 
                WHERE spot_id = ? AND status = ? 
                AND start_time < ? AND end_time > ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spotId, ReservationStatus::BOOKED, $endTime, $startTime]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a reservation. Must be called within a transaction after acquiring locks.
     */
    public function create(int $userId, int $spotId, string $startTime, string $endTime): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO reservations (user_id, spot_id, start_time, end_time, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $spotId, $startTime, $endTime, ReservationStatus::BOOKED]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Mark reservation as completed.
     */
    public function markCompleted(int $id, int $userId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE reservations SET status = ? WHERE id = ? AND user_id = ? AND status = ?'
        );
        $stmt->execute([ReservationStatus::COMPLETED, $id, $userId, ReservationStatus::BOOKED]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark reservation as completed (for background worker - no user check).
     */
    public function markCompletedById(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE reservations SET status = ? WHERE id = ? AND status = ?');
        $stmt->execute([ReservationStatus::COMPLETED, $id, ReservationStatus::BOOKED]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get reservation by ID.
     */
    public function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.id, r.user_id, r.spot_id, r.start_time, r.end_time, r.status 
             FROM reservations r WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find stale reservations (Booked but end_time has passed).
     */
    public function findStale(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, spot_id, start_time, end_time 
             FROM reservations 
             WHERE status = ? AND end_time < NOW()'
        );
        $stmt->execute([ReservationStatus::BOOKED]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all Booked reservations for a given date (for API/display).
     */
    public function findBookedByDate(string $date): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT r.id, r.user_id, r.spot_id, r.start_time, r.end_time, r.status 
             FROM reservations r 
             WHERE r.status = ?
             AND DATE(r.start_time) = ? 
             ORDER BY r.spot_id, r.start_time'
        );
        $stmt->execute([ReservationStatus::BOOKED, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
