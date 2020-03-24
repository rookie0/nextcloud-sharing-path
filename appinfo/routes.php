<?php

namespace OCA\SharingPath\AppInfo;

$app = new Application();
$app->registerRoutes(
    $this,
    [
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
    ]
);
