<?php

return [
    'routing' => [
        'mode' => env('SHEETS_MODE', 'path'),
        'prefix' => 'sheets',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'sheets.dashboard',
        'icon'  => 'heroicon-o-table-cells',
        'order' => 100,
    ],

    'sidebar' => [
        [
            'group' => 'Sheets',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'sheets.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
            ],
        ],
    ],
];
