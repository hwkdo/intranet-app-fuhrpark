<?php

declare(strict_types=1);
use App\Models\Standort;
use App\Models\User;

return [
    'user_model' => env('FUHRPARK_USER_MODEL', User::class),
    'standort_model' => env('FUHRPARK_STANDORT_MODEL', Standort::class),

    'limits' => [
        'max_booking_days' => 10,
        'max_open_logbook' => 3,
        'max_no_show' => 3,
    ],

    'utilization' => [
        'business_hour_start' => 7,
        'business_hour_end' => 18,
        /** @var list<int> ISO weekday: 1 = Monday … 7 = Sunday */
        'business_days' => [1, 2, 3, 4, 5],
    ],

    'roles' => [
        'admin' => [
            'name' => 'App-Fuhrpark-Admin',
            'permissions' => [
                'see-app-fuhrpark',
                'manage-app-fuhrpark',
            ],
        ],
        'zentrale' => [
            'name' => 'App-Fuhrpark-Zentrale',
            'permissions' => [
                'see-app-fuhrpark',
                'operate-app-fuhrpark-zentrale',
            ],
        ],
        'fuehrerschein' => [
            'name' => 'App-Fuhrpark-Fuehrerschein',
            'permissions' => [
                'see-app-fuhrpark',
                'manage-app-fuhrpark-driver-licenses',
            ],
        ],
        'projekt-empfaenger' => [
            'name' => 'App-Fuhrpark-Projekt-Empfaenger',
            'permissions' => [
                'see-app-fuhrpark',
                'operate-app-fuhrpark-projekt-empfaenger',
            ],
        ],
        'user' => [
            'name' => 'App-Fuhrpark-Benutzer',
            'permissions' => [
                'see-app-fuhrpark',
            ],
            'all_users' => true,
        ],
    ],
];
