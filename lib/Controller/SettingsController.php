<?php

namespace OCA\SharingPath\Controller;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller
{

    private $config;
    private $userId;

    public function __construct(IRequest $request, IConfig $config, string $userId)
    {
        parent::__construct(Application::APP_ID, $request);

        $this->config = $config;
        $this->userId = $userId;
    }

    /**
     * @NoAdminRequired
     */
    public function enable(string $enabled)
    {
        $this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_ENABLE, $enabled);
        return new JSONResponse();
    }
}
