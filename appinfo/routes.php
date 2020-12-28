<?php

return [
    'routes' => [
        [
            'name' => 'Path#index',
            'url'  => '/',
            'verb' => 'GET',
        ],
        [
            'name' => 'Settings#index',
            'url'  => '/settings',
            'verb' => 'GET',
        ],
        [
            'name' => 'Settings#enable',
            'url'  => '/settings/enable',
            'verb' => 'PUT',
        ],
        [
            'name' => 'Settings#setCopyPrefix',
            'url'  => '/settings/copyprefix',
            'verb' => 'PUT',
        ],
        [
            'name' => 'Settings#setSharingFolder',
            'url'  => '/settings/sharingfolder',
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
