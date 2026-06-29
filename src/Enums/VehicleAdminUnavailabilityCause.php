<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Enums;

enum VehicleAdminUnavailabilityCause: string
{
    case Deactivated = 'deactivated';
    case Lock = 'lock';
    case AvailabilityWindow = 'availability_window';
    case Workshop = 'workshop';

    public function menuAction(): string
    {
        return match ($this) {
            self::Deactivated => 'deactivate',
            self::Lock => 'lock',
            self::AvailabilityWindow => 'availability',
            self::Workshop => 'workshop',
        };
    }
}
