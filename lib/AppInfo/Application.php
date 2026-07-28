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
use OCP\Util;
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
        $loadScript = function () {
            if (method_exists(Util::class, 'addInitScript')) {
                // Nextcloud >= 28: load before the files app bundles, so the
                // file action is registered before the action list is built
                Util::addInitScript(self::APP_ID, 'script');
            } else {
                Util::addScript(self::APP_ID, 'script');
            }
        };

        /** @var IEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get(IEventDispatcher::class);
        // Nextcloud >= 20 dispatches the typed event (class name as event name,
        // referenced as string so nothing breaks if the files app is disabled)
        $dispatcher->addListener('OCA\Files\Event\LoadAdditionalScriptsEvent', $loadScript);
        // legacy string event for old releases
        $dispatcher->addListener('OCA\Files::loadAdditionalScripts', $loadScript);
    }

}
