<?php

namespace OCA\SharingPath\Settings;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class Admin implements ISettings
{

    private $config;
    private $l;

    public function __construct(IConfig $config, IL10N $l)
    {
        $this->config = $config;
        $this->l = $l;
    }

    public function getForm()
    {
        $enabled = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
        $prefix = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX);
        $folder = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);

        return new TemplateResponse(Application::APP_ID, 'settings/admin', [
            'enabled' => $enabled,
            'prefix'  => $prefix,
            'folder'  => $folder,
        ]);
    }

    public function getSection()
    {
        return 'sharing';
    }

    public function getPriority()
    {
        return 100;
    }

}

