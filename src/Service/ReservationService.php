<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ParkingSpotRepository;
use App\Repository\ReservationRepository;
use App\Service\PubSub\PubSubInterface;

class ReservationService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ParkingSpotRepository $parkingSpotRepository,
        private ?PubSubInterface $pubSub = null
    ) {
    }

    /**
     * Create a reservation with pessimistic locking to prevent double-booking.
     * Uses SELECT ... FOR UPDATE within a transaction to serialize concurrent requests.
     */
    public function create(int $userId, int $spotId, string $startTime, string $endTime): array
    {
        $this->validateTimeRange($startTime, $endTime);

        $spot = $this->parkingSpotRepository->findById($spotId);
        if (!$spot) {
            throw new \InvalidArgumentException('Invalid spot_id');
        }

        $pdo = \App\Database\Database::getConnection();

        try {
            $pdo->beginTransaction();

            // Lock overlapping rows - blocks concurrent requests until we commit/rollback
            $overlapping = $this->reservationRepository->findOverlappingBooked(
                $spotId,
                $startTime,
                $endTime,
                true
            );

            if (!empty($overlapping)) {
                $pdo->rollBack();
                throw new \RuntimeException(
                    'This time slot is no longer available. Another user has just reserved it.'
                );
            }

            $id = $this->reservationRepository->create($userId, $spotId, $startTime, $endTime);
            $pdo->commit();

            $reservation = $this->reservationRepository->findById($id);
            $this->publishReservationChange('created', $reservation);

            return $reservation;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function complete(int $reservationId, int $userId): array
    {
        $reservation = $this->reservationRepository->findById($reservationId);
        if (!$reservation) {
            throw new \InvalidArgumentException('Reservation not found');
        }
        if ((int) $reservation['user_id'] !== $userId) {
            throw new \InvalidArgumentException('You can only complete your own reservations');
        }
        if ($reservation['status'] !== 'Booked') {
            throw new \InvalidArgumentException('Reservation is already completed');
        }

        $updated = $this->reservationRepository->markCompleted($reservationId, $userId);
        if (!$updated) {
            throw new \RuntimeException('Failed to complete reservation');
        }

        $reservation['status'] = 'Completed';
        $this->publishReservationChange('completed', $reservation);

        return $reservation;
    }

    public function getSpots(): array
    {
        return $this->parkingSpotRepository->findAll();
    }

    public function getReservationsForDate(string $date): array
    {
        return $this->reservationRepository->findBookedByDate($date);
    }

    private function validateTimeRange(string $startTime, string $endTime): void
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        if ($start === false || $end === false || $start >= $end) {
            throw new \InvalidArgumentException('Invalid start_time or end_time');
        }
    }

    private function publishReservationChange(string $change, array $reservation): void
    {
        if ($this->pubSub) {
            $this->pubSub->publish('reservation_change', [
                'change' => $change,
                'reservation' => $reservation,
            ]);
        }
    }
}
