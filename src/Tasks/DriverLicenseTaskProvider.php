<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppFuhrpark\IntranetAppFuhrpark;
use Hwkdo\IntranetAppFuhrpark\Services\DriverLicenseService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class DriverLicenseTaskProvider implements TaskProviderInterface
{
    public function __construct(
        private readonly DriverLicenseService $driverLicenseService,
    ) {}

    public function getLabel(): string
    {
        return 'Führerschein verlängern';
    }

    public function getTasksForUser(Authenticatable $user): Collection
    {
        $isValid = $this->driverLicenseService->isValid($user);
        $isExpiringSoon = $this->driverLicenseService->isExpiringSoon($user);

        if ($isValid && ! $isExpiringSoon) {
            return collect();
        }

        $description = $isValid
            ? 'Führerschein läuft in Kürze ab – bitte Personalabteilung kontaktieren'
            : 'Kein gültiger Führerschein hinterlegt';

        return collect([
            new TaskItem(
                title: 'Führerschein prüfen / verlängern',
                url: route('apps.fuhrpark.info'),
                appIdentifier: IntranetAppFuhrpark::identifier(),
                appName: IntranetAppFuhrpark::app_name(),
                appIcon: IntranetAppFuhrpark::app_icon(),
                description: $description,
                priority: 70,
            ),
        ]);
    }
}
