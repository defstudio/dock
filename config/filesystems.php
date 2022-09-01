<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
        'cwd' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
        'configs' => [
            'driver' => 'local',
            'root'   => getcwd()."/configs",
        ],
        'src' => [
            'driver' => 'local',
            'root'   => getcwd()."/src",
        ]
    ]
];
