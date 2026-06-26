<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark;

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppFuhrpark\Data\AppSettings;
use Hwkdo\IntranetAppFuhrpark\Data\UserSettings;
use Hwkdo\IntranetAppFuhrpark\Tasks\DriverLicenseTaskProvider;
use Hwkdo\IntranetAppFuhrpark\Tasks\MissingLogbookTaskProvider;
use Hwkdo\IntranetAppFuhrpark\Tasks\NoShowBookingTaskProvider;
use Illuminate\Support\Collection;

class IntranetAppFuhrpark implements IntranetAppInterface, ProvidesTasksInterface
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
        return UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
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
}
