<?php

namespace OCA\SharingPath\Controller;

use OC;
use OC_Response;
use OC\Files\Filesystem;
use OC\Share\Share;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use OCP\Share\IManager;

class PathController extends Controller
{
    private $userManager;
    private $shareManager;
    private $rootFolder;

    public function __construct($AppName,
                                IRequest $request,
                                IUserManager $userManager,
                                IManager $shareManager,
                                IRootFolder $rootFolder)
    {
        parent::__construct($AppName, $request);
        $this->userManager  = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder   = $rootFolder;
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index()
    {
        http_response_code(404);
        exit;
    }

    /**
     * CAUTION: the @Stuff turns off security checks; for this page no admin is
     *          required and no CSRF check. If you don't know what CSRF is, read
     *          it up in the docs or you might create a security hole. This is
     *          basically the only required method to add this exemption, don't
     *          add it to any other method if you don't exactly know what it does
     *
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function handle($uid, $path)
    {
        // check user & path exist
        $user = $this->userManager->get($uid);
        if (! $user || ! $path) {
            http_response_code(404);
            exit;
        }

        try {
            $userFolder = $this->rootFolder->getUserFolder($uid);
            // check file or file dirs is shared
            if (! $this->isShared($uid, $path)) {
                http_response_code(404);
                exit;
            }

            // todo version file handle

            $path = $userFolder->getRelativePath($userFolder->get($path)->getPath());

            // output file contents without Content-Disposition header
            header('Content-Transfer-Encoding: binary', true);
            header('Pragma: public');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

            \OC_Util::setupFS($uid);
            $fileSize = Filesystem::filesize($path);
            $type     = OC::$server->getMimeTypeDetector()->getSecureMimeType(Filesystem::getMimeType($path));
            if ($fileSize > -1) {
                OC_Response::setContentLengthHeader($fileSize);
            }
            header('Content-Type: ' . $type, true);
            Filesystem::getView()->readfile($path);
        } catch (NotFoundException $e) {
            http_response_code(404);
            exit;
        }
    }

    private function isShared($uid, $path)
    {
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $len      = count($segments);
        $now      = time();
        $shared   = false;
        for ($i = $len; $i > 0; $i--) {
            $tmpPath  = implode(DIRECTORY_SEPARATOR, array_slice($segments, 0, $i));
            $userPath = $this->rootFolder->getUserFolder($uid)->get($tmpPath);
            $shares   = $this->shareManager->getSharesBy($uid, Share::SHARE_TYPE_LINK, $userPath);
            $share    = $shares[0] ?? null;

            // shared but checked hide download or password protect or expired
            if ($share && (
                    $share->getHideDownload() ||
                    $share->getPassword() || (
                        $share->getExpirationDate() &&
                        $share->getExpirationDate()->getTimestamp() < $now))) {
                return false;
            } elseif ($share) {
                $shared = true;
            }
        }

        return $shared;
    }

}
