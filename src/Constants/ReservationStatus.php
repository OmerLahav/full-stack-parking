<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Reservation status values and WebSocket channel for real-time updates.
 */
final class ReservationStatus
{
    public const BOOKED = 'Booked';
    public const COMPLETED = 'Completed';

    public const WS_CHANNEL = 'reservation_change';
}
