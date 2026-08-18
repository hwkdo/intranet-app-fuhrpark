<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppFuhrpark\Dashboard;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;

class FuhrparkDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    public const KEY_KOMMENDE_BUCHUNGEN = 'kommende-buchungen';

    /**
     * @return array<DashboardWidgetDefinition>
     */
    public static function widgets(): array
    {
        return [
            new DashboardWidgetDefinition(
                key: self::KEY_KOMMENDE_BUCHUNGEN,
                title: 'Kommende Buchungen',
                description: 'Ihre nächsten Fahrzeugbuchungen mit Beginn, Zweck und Kennzeichen',
                component: 'intranet-app-fuhrpark::apps.fuhrpark.widgets.kommende-buchungen',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
            ),
        ];
    }
}
