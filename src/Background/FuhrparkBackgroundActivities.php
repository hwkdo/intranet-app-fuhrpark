<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Mail\BookingCancelledAdminMail;
use Hwkdo\IntranetAppFuhrpark\Mail\BookingCancelledMail;
use Hwkdo\IntranetAppFuhrpark\Mail\ProjectTripChangedMail;
use Hwkdo\IntranetAppFuhrpark\Mail\ProjectTripRecordedMail;
use Hwkdo\IntranetAppFuhrpark\Mail\VehicleDamageReportedMail;

final class FuhrparkBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppFuhrpark::identifier();
        $name = IntranetAppFuhrpark::app_name();

        return [
            BackgroundActivityDefinition::mail(key: 'fuhrpark.mail-booking-cancelled', label: 'Mail: Buchung storniert', appIdentifier: $id, appName: $name, mailable: BookingCancelledMail::class, sort: 10),
            BackgroundActivityDefinition::mail(key: 'fuhrpark.mail-booking-cancelled-admin', label: 'Mail: Storno an Admin', appIdentifier: $id, appName: $name, mailable: BookingCancelledAdminMail::class, sort: 20),
            BackgroundActivityDefinition::mail(key: 'fuhrpark.mail-project-trip', label: 'Mail: Dienstfahrt erfasst', appIdentifier: $id, appName: $name, mailable: ProjectTripRecordedMail::class, sort: 30),
            BackgroundActivityDefinition::mail(key: 'fuhrpark.mail-project-trip-changed', label: 'Mail: Dienstfahrt geändert', appIdentifier: $id, appName: $name, mailable: ProjectTripChangedMail::class, sort: 40),
            BackgroundActivityDefinition::mail(key: 'fuhrpark.mail-damage', label: 'Mail: Schaden gemeldet', appIdentifier: $id, appName: $name, mailable: VehicleDamageReportedMail::class, sort: 50),
        ];
    }
}
