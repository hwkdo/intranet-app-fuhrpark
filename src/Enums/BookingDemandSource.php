<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Enums;

enum BookingDemandSource: string
{
    case Create = 'create';
    case Reschedule = 'reschedule';
    case Preview = 'preview';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Buchungsversuch',
            self::Reschedule => 'Umbuchung',
            self::Preview => 'Kalender-Vorschau',
        };
    }
}
