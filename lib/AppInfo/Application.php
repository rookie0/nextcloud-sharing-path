<?php

namespace OCA\SharingPath\AppInfo;

use OCA\SharingPath\Controller\PathController;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap
{

    const APP_ID = 'sharingpath';

    const SETTINGS_KEY_DEFAULT_ENABLE         = 'default_enabled';
    const SETTINGS_KEY_ENABLE                 = 'enabled';
    const SETTINGS_KEY_DEFAULT_COPY_PREFIX    = 'default_copy_prefix';
    const SETTINGS_KEY_COPY_PREFIX            = 'copy_prefix';
    const SETTINGS_KEY_DEFAULT_SHARING_FOLDER = 'default_sharing_folder';
    const SETTINGS_KEY_SHARING_FOLDER         = 'sharing_folder';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);

    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerService('PathController', function (ContainerInterface $c) {
            return new PathController(
                $c->get('AppName'),
                $c->get(IRequest::class),
                $c->get(IConfig::class),
                $c->get(IUserManager::class),
                $c->get(IShareManager::class),
                $c->get(IRootFolder::class),
                $c->get(LoggerInterface::class),
                $c->get(IMimeTypeDetector::class)
            );
        });
    }

    public function boot(IBootContext $context): void
    {
        $this->getContainer()->get(IEventDispatcher::class)->addListener('OCA\Files::loadAdditionalScripts', function () {
            script(self::APP_ID, 'script');
        });
    }

}