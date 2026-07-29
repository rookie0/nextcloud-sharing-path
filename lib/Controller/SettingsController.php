<?php

namespace OCA\SharingPath\Controller;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;

class SettingsController extends Controller
{

    private $config;
    private $userId;
    private $groupManager;

    public function __construct(IRequest $request, IConfig $config, IGroupManager $groupManager, string $userId)
    {
        parent::__construct(Application::APP_ID, $request);

        $this->config = $config;
        $this->userId = $userId;
        $this->groupManager = $groupManager;
    }

    /**
     * Whether this request may write the instance wide defaults.
     *
     * The request tells us which form it came from, but that claim is not
     * trustworthy on its own — group membership is what decides.
     */
    private function writesAppDefaults(): bool
    {
        return $this->request->getParam('type') === 'admin';
    }

    private function forbidden(): JSONResponse
    {
        return new JSONResponse(['message' => 'Admin privileges required'], Http::STATUS_FORBIDDEN);
    }

    private function mayWriteAppDefaults(): bool
    {
        return $this->groupManager->isAdmin($this->userId);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function index()
    {
        return new JSONResponse([
            Application::SETTINGS_KEY_DEFAULT_ENABLE      => $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE),
            Application::SETTINGS_KEY_ENABLE              => $this->config->getUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_ENABLE),
            Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX => $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX),
            Application::SETTINGS_KEY_COPY_PREFIX         => $this->config->getUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_COPY_PREFIX),
        ]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function enable(string $enabled)
    {
        if ($this->writesAppDefaults()) {
            if (! $this->mayWriteAppDefaults()) {
                return $this->forbidden();
            }
            $this->config->setAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE, $enabled);
        } else {
            $this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_ENABLE, $enabled);
        }

        return new JSONResponse();
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function setCopyPrefix(string $prefix)
    {
        if ($this->writesAppDefaults()) {
            if (! $this->mayWriteAppDefaults()) {
                return $this->forbidden();
            }
            $this->config->setAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_COPY_PREFIX, trim($prefix));
        } else {
            $this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_COPY_PREFIX, trim($prefix));
        }

        return new JSONResponse();
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function setSharingFolder(string $folder)
    {
        if ($this->writesAppDefaults()) {
            if (! $this->mayWriteAppDefaults()) {
                return $this->forbidden();
            }
            $this->config->setAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER, trim($folder));
        } else {
            $this->config->setUserValue($this->userId, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER, trim($folder));
        }

        return new JSONResponse();
    }
}
