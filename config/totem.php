<?php

return [
    'frequencies' => [
        [
            'label' => 'Every Minute',
            'interval' => 'everyMinute',
            'parameters' => false,
        ],
        [
            'label' => 'Every Five Minutes',
            'interval' => 'everyFiveMinutes',
            'parameters' => false,
        ],
        [
            'label' => 'Every Ten Minutes',
            'interval' => 'everyTenMinutes',
            'parameters' => false,
        ],
        [
            'label' => 'Every Thirty Minutes',
            'interval' => 'everyThirtyMinutes',
            'parameters' => false,
        ],
        [
            'label' => 'Hourly',
            'interval' => 'hourly',
            'parameters' => false,
        ],
        [
            'label' => 'Hourly at',
            'interval' => 'hourlyAt',
            'parameters' => [
                [
                    'label' => 'At',
                    'name' => 'at',
                    'type' => 'number',
                    'min' => '0',
                    'max' => '59',
                ],
            ],
        ],
        [
            'label' => 'Daily',
            'interval' => 'daily',
            'parameters' => false,
        ],
        [
            'label' => 'Daily at',
            'interval' => 'dailyAt',
            'parameters' => [
                [
                    'label' => 'At',
                    'name' => 'at',
                    'type' => 'time',
                ],
            ],
        ],
        [
            'label' => 'Twice Daily',
            'interval' => 'twiceDaily',
            'parameters' => [
                [
                    'label' => 'First',
                    'name' => 'at',
                    'type' => 'time',
                ],
                [
                    'label' => 'Second',
                    'name' => 'second_at',
                    'type' => 'time',
                ],
            ],
        ],
        [
            'label' => 'Weekly',
            'interval' => 'weekly',
            'parameters' => false,
        ],
        [
            'label' => 'Weekly On',
            'interval' => 'weeklyOn',
            'parameters' => [
                [
                    'label' => 'On',
                    'name' => 'on',
                    'type' => 'number',
                    'min' => '1',
                    'max' => '31',
                ],
                [
                    'label' => 'At',
                    'name' => 'at',
                    'type' => 'time',
                ],
            ],
        ],
        [
            'label' => 'Monthly',
            'interval' => 'monthly',
            'parameters' => false,
        ],
        [
            'label' => 'Monthly On',
            'interval' => 'monthlyOn',
            'parameters' => [
                [
                    'label' => 'On',
                    'name' => 'on',
                    'type' => 'number',
                    'max' => '',
                ],
                [
                    'label' => 'At',
                    'name' => 'at',
                    'type' => 'time',
                ],
            ],
        ],
        [
            'label' => 'Twice Monthly',
            'interval' => 'twiceMonthly',
            'parameters' => [
                [
                    'label' => 'First',
                    'name' => 'on',
                    'type' => 'number',
                ],
                [
                    'label' => 'Second',
                    'name' => 'second_at',
                    'type' => 'text',
                ],
            ],
        ],
        [
            'label' => 'Quarterly',
            'interval' => 'quarterly',
            'parameters' => false,
        ],
        [
            'label' => 'Yearly',
            'interval' => 'yearly',
            'parameters' => false,
        ],
        [
            'label' => 'Weekdays',
            'interval' => 'weekdays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Sunday',
            'interval' => 'sundays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Monday',
            'interval' => 'mondays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Tuesday',
            'interval' => 'tuesdays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Wednesday',
            'interval' => 'wednesdays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Thursday',
            'interval' => 'thursdays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Friday',
            'interval' => 'fridays',
            'parameters' => false,
        ],
        [
            'label' => 'Every Saturday',
            'interval' => 'saturdays',
            'parameters' => false,
        ],
        [
            'label' => 'Between',
            'interval' => 'between',
            'parameters' => [
                [
                    'label' => 'Start',
                    'name' => 'start',
                    'type' => 'time',
                ],
                [
                    'label' => 'End',
                    'name' => 'end',
                    'type' => 'time',
                ],
            ],
        ],
        [
            'label' => 'Unless Between',
            'interval' => 'unlessBetween',
            'parameters' => [
                [
                    'label' => 'Start',
                    'name' => 'start',
                    'type' => 'time',
                ],
                [
                    'label' => 'End',
                    'name' => 'end',
                    'type' => 'time',
                ],
            ],
        ],
    ],
    'web' => [
        'middleware' => env('TOTEM_WEB_MIDDLEWARE', 'web'),
        'route_prefix' => env('TOTEM_WEB_ROUTE_PREFIX', 'totem'),
    ],
    'api' => [
        'middleware' => env('TOTEM_API_MIDDLEWARE', 'api'),
    ],
    'table_prefix' => env('TOTEM_TABLE_PREFIX', ''),
    'artisan' => [
        'command_filter' => [],
        'whitelist' => true,
    ],
    'database_connection' => env('TOTEM_DATABASE_CONNECTION'),

    'broadcasting' => [
        'enabled' => env('TOTEM_BROADCASTING_ENABLED', true),
        'channel' => env('TOTEM_BROADCASTING_CHANNEL', 'task.events'),
    ],

    'cache_store' => env('TOTEM_CACHE_STORE', null),
];
