<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Enums;

enum BookingPurpose: string
{
    case Normal = 'normal';
    case Workshop = 'workshop';
    case Lock = 'lock';
    case ChargeLock = 'charge_lock';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normale Buchung',
            self::Workshop => 'Werkstattfahrt',
            self::Lock => 'Sperre',
            self::ChargeLock => 'Laden',
        };
    }

    public function isBlocking(): bool
    {
        return in_array($this, [self::Lock, self::ChargeLock], true);
    }
}
