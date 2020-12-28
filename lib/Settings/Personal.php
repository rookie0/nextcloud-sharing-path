<?php

namespace OCA\SharingPath\Settings;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class Personal implements ISettings
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
        $uid = \OC_User::getUser();
        $enabled = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_ENABLE);
        $defaultEnabled = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
        $prefix = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_COPY_PREFIX);
        $defaultPrefix = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX);
        $folder = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER);
        $defaultFolder = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);

        return new TemplateResponse(Application::APP_ID, 'settings/personal', [
            'enabled'         => $enabled,
            'default_enabled' => $defaultEnabled,
            'prefix'          => $prefix,
            'default_prefix'  => $defaultPrefix ? (trim($defaultPrefix, '/') . '/' . $uid) : '',
            'folder'          => $folder,
            'default_folder'  => $defaultFolder,
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

