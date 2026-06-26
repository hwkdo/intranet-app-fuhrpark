<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Enums;

enum BookingStatus: string
{
    case Reserved = 'reserved';
    case HandedOut = 'handed_out';
    case Returned = 'returned';
    case Completed = 'completed';
    case Overdue = 'overdue';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'In Zukunft',
            self::HandedOut => 'Abgeholt',
            self::Returned => 'Zurückgegeben',
            self::Completed => 'Abgeschlossen',
            self::Overdue => 'Verspätet',
            self::NoShow => 'Nicht angetreten',
        };
    }
}
