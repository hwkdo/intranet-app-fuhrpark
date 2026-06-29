<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Enums;

enum BookingDemandReason: string
{
    case NoVehicleInCategory = 'no_vehicle_in_category';
    case VehicleUnavailable = 'vehicle_unavailable';
    case AllCategoriesUnavailable = 'all_categories_unavailable';
    case RescheduleUnavailable = 'reschedule_unavailable';

    public function label(): string
    {
        return match ($this) {
            self::NoVehicleInCategory => 'Kein Fahrzeug in der Kategorie',
            self::VehicleUnavailable => 'Gewähltes Fahrzeug nicht verfügbar',
            self::AllCategoriesUnavailable => 'Keine Kategorie verfügbar',
            self::RescheduleUnavailable => 'Umbuchung nicht möglich',
        };
    }
}
