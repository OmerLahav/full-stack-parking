<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Parking time slots per day (08:00-12:00, 12:00-16:00, 16:00-20:00).
 */
final class TimeSlots
{
    public const SLOTS = [
        ['start' => '08:00', 'end' => '12:00'],
        ['start' => '12:00', 'end' => '16:00'],
        ['start' => '16:00', 'end' => '20:00'],
    ];
}
