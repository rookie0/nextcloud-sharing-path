<?php

return [
    'routes' => [
        [
            'name' => 'Path#index',
            'url'  => '/',
            'verb' => 'GET',
        ],
        [
            'name' => 'Settings#enable',
            'url'  => '/settings/enable',
            'verb' => 'PUT',
        ],
        [
            'name'         => 'Path#handle',
            'url'          => '/{uid}/{path}',
            'verb'         => 'GET',
            'requirements' => ['uid' => '[^\/]+', 'path' => '.*'],
        ],
    ],
];
