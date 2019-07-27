<?php

namespace OCA\SharingPath\AppInfo;

$app = new Application();
$app->registerRoutes(
    $this,
    [
        'routes' => [
            [
                'name'         => 'Path#index',
                'url'          => '/{uid}/{path}',
                'verb'         => 'GET',
                'requirements' => ['uid' => '[^\/]+', 'path' => '.+'],
            ],
        ],
    ]
);
