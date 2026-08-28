<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark;

use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppFuhrpark\Dashboard\FuhrparkDashboardWidgetProvider;
use Hwkdo\IntranetAppFuhrpark\Data\AppSettings;
use Hwkdo\IntranetAppFuhrpark\Mcp\Servers\FuhrparkServer;
use Hwkdo\IntranetAppFuhrpark\Tasks\DriverLicenseTaskProvider;
use Hwkdo\IntranetAppFuhrpark\Tasks\MissingLogbookTaskProvider;
use Hwkdo\IntranetAppFuhrpark\Tasks\NoShowBookingTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppFuhrpark implements IntranetAppInterface, ProvidesDashboardWidgetsInterface, ProvidesNotificationsInterface, ProvidesTasksInterface
{
    public static function app_name(): string
    {
        return 'Fuhrpark';
    }

    public static function app_icon(): string
    {
        return 'truck';
    }

    public static function identifier(): string
    {
        return 'fuhrpark';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-fuhrpark.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-fuhrpark.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return null;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [
            'fuhrpark' => [
                'class' => FuhrparkServer::class,
                'middleware' => ['auth:api'],
            ],
        ];
    }

    /**
     * @return array<class-string<TaskProviderInterface>>
     */
    public static function taskProviders(): array
    {
        return [
            MissingLogbookTaskProvider::class,
            NoShowBookingTaskProvider::class,
            DriverLicenseTaskProvider::class,
        ];
    }

    /**
     * @return array<class-string<DashboardWidgetProviderInterface>>
     */
    public static function dashboardWidgetProviders(): array
    {
        return [
            FuhrparkDashboardWidgetProvider::class,
        ];
    }

    public static function notificationTypes(): array
    {
        return [
            new NotificationTypeDefinition(
                key: 'fuhrpark.booking_cancelled',
                label: 'Buchung gelöscht',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                mandatory: true,
            ),
            new NotificationTypeDefinition(
                key: 'fuhrpark.booking_cancelled_admin',
                label: 'Buchung gelöscht (Admin)',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                mandatory: false,
                defaultEnabled: true,
            ),
            new NotificationTypeDefinition(
                key: 'fuhrpark.vehicle_damage',
                label: 'Fahrzeugschaden gemeldet',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                mandatory: true,
            ),
            new NotificationTypeDefinition(
                key: 'fuhrpark.project_trip',
                label: 'Projektfahrt erfasst',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                mandatory: false,
                defaultEnabled: true,
            ),
            new NotificationTypeDefinition(
                key: 'fuhrpark.project_trip_changed',
                label: 'Projektfahrt geändert',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                mandatory: false,
                defaultEnabled: true,
            ),
        ];
    }
}
