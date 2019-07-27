<?php

namespace OCA\SharingPath\AppInfo;

use OCA\SharingPath\Controller\PathController;
use OCP\AppFramework\App;
use OCP\IContainer;

class Application extends App
{

    public function __construct(array $urlParams = [])
    {
        parent::__construct('sharingpath', $urlParams);

        $container = $this->getContainer();
        $server    = $container->getServer();

        $container->registerService('PathController', function (IContainer $c) use ($server) {
            return new PathController(
                $c->query('AppName'),
                $c->query('Request'),
                $server->getUserManager(),
                $server->getShareManager(),
                $server->getRootFolder()
            );
        });
    }

}