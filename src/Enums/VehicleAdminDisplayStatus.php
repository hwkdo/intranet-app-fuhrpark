<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Enums;

enum VehicleAdminDisplayStatus: string
{
    case Available = 'available';
    case Limited = 'limited';
    case Underway = 'underway';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Verfügbar',
            self::Limited => 'Eingeschränkt',
            self::Underway => 'Unterwegs',
            self::Unavailable => 'Nicht verfügbar',
        };
    }

    public function rowClass(): string
    {
        return match ($this) {
            self::Available => 'bg-green-50/90 dark:bg-green-950/30',
            self::Limited => 'bg-amber-50/90 dark:bg-amber-950/30',
            self::Underway => 'bg-sky-50/90 dark:bg-sky-950/30',
            self::Unavailable => 'bg-red-50/90 dark:bg-red-950/30',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Limited => 'amber',
            self::Underway => 'sky',
            self::Unavailable => 'red',
        };
    }

    public function statusButtonVariant(): string
    {
        return match ($this) {
            self::Unavailable => 'danger',
            default => 'ghost',
        };
    }

    public function statusButtonClass(): string
    {
        return match ($this) {
            self::Limited => 'border border-amber-300 dark:border-amber-600',
            default => '',
        };
    }

    public function filterDotClass(): string
    {
        return match ($this) {
            self::Available => 'bg-green-500',
            self::Limited => 'bg-amber-500',
            self::Underway => 'bg-sky-500',
            self::Unavailable => 'bg-red-500',
        };
    }
}
