<?php

namespace OCA\SharingPath\Controller;

use OC;
use OC_Response;
use OC\Files\Filesystem;
use OCA\SharingPath\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\UnseekableException;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;

class PathController extends Controller
{
    private $config;
    private $userManager;
    private $shareManager;
    private $rootFolder;
    private $logger;

    public function __construct($appName,
                                IRequest $request,
                                IConfig $config,
                                IUserManager $userManager,
                                IManager $shareManager,
                                IRootFolder $rootFolder,
                                ILogger $logger)
    {
        parent::__construct($appName, $request);

        $this->config       = $config;
        $this->userManager  = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder   = $rootFolder;
        $this->logger       = $logger;
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @NoSameSiteCookieRequired
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
     * @NoSameSiteCookieRequired
     */
    public function handle($uid, $path)
    {
        // check user & path exist
        $user = $this->userManager->get($uid);
        if (! $user || ! $path) {
            http_response_code(404);
            exit;
        }

        // check use is enabled sharing path
        if ($this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_ENABLE, 'yes') !== 'yes') {
            http_response_code(403);
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

            \OC_Util::setupFS($uid);
            $path     = $userFolder->getRelativePath($userFolder->get($path)->getPath());
            $fileSize = Filesystem::filesize($path);

            $rangeArray = [];
            if (isset($_SERVER['HTTP_RANGE']) &&
                substr(OC::$server->getRequest()->getHeader('Range'), 0, 6) === 'bytes=') {
                $rangeArray = self::parseHttpRangeHeader(substr(OC::$server->getRequest()->getHeader('Range'), 6), $fileSize);
            }

            self::sendHeaders($path, $rangeArray);

            if (OC::$server->getRequest()->getMethod() === 'HEAD') {
                return;
            }

            $view = Filesystem::getView();
            if (! empty($rangeArray)) {
                try {
                    if (count($rangeArray) == 1) {
                        $view->readfilePart($path, $rangeArray[0]['from'], $rangeArray[0]['to']);
                    } else {
                        // check if file is seekable (if not throw UnseekableException)
                        // we have to check it before body contents
                        $view->readfilePart($path, $rangeArray[0]['size'], $rangeArray[0]['size']);

                        $type = OC::$server->getMimeTypeDetector()->getSecureMimeType(Filesystem::getMimeType($path));

                        foreach ($rangeArray as $range) {
                            echo "\r\n--" . self::getBoundary() . "\r\n" .
                                "Content-type: " . $type . "\r\n" .
                                "Content-range: bytes " . $range['from'] . "-" . $range['to'] . "/" . $range['size'] . "\r\n\r\n";
                            $view->readfilePart($path, $range['from'], $range['to']);
                        }
                        echo "\r\n--" . self::getBoundary() . "--\r\n";
                    }
                } catch (UnseekableException $ex) {
                    // file is unseekable
                    header_remove('Accept-Ranges');
                    header_remove('Content-Range');
                    http_response_code(200);
                    self::sendHeaders($path, array());
                    $view->readfile($path);
                }
            }

            // FIXME: The exit is required here because otherwise the AppFramework is trying to add headers as well
            $view->readfile($path);
            exit;
        } catch (NotFoundException $e) {
            http_response_code(404);
            $this->logger->warning("request user {$uid} file {$path} not found.", ['app' => Application::APP_ID]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            $this->logger->error("request user {$uid} file {$path} failed: " . $e->getMessage(), [
                'app'           => Application::APP_ID,
                'extra_context' => $e->getTrace(),
            ]);
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
            $tmpPath   = implode(DIRECTORY_SEPARATOR, array_slice($segments, 0, $i));
            $userPath  = $this->rootFolder->getUserFolder($uid)->get($tmpPath);
            $shareType = version_compare(\OC_Util::getVersionString(), '17.0.0', '>=') ? IShare::TYPE_LINK : OC\Share\Constants::SHARE_TYPE_LINK;
            $shares    = $this->shareManager->getSharesBy($uid, $shareType, $userPath);
            $share     = $shares[0] ?? null;

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

    /**
     * Copy from OC_Files without setContentDispositionHeader
     * @param string $filename
     * @param array  $rangeArray ('from'=>int,'to'=>int), ...
     */
    private static function sendHeaders($filename, array $rangeArray)
    {
        header('Content-Transfer-Encoding: binary', true);
        header('Pragma: public');// enable caching in IE
        header('Expires: 0');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        $fileSize = \OC\Files\Filesystem::filesize($filename);
        $type     = \OC::$server->getMimeTypeDetector()->getSecureMimeType(\OC\Files\Filesystem::getMimeType($filename));
        if ($fileSize > -1) {
            if (! empty($rangeArray)) {
                http_response_code(206);
                header('Accept-Ranges: bytes', true);
                if (count($rangeArray) > 1) {
                    $type = 'multipart/byteranges; boundary=' . self::getBoundary();
                    // no Content-Length header here
                } else {
                    header(sprintf('Content-Range: bytes %d-%d/%d', $rangeArray[0]['from'], $rangeArray[0]['to'], $fileSize), true);
                    OC_Response::setContentLengthHeader($rangeArray[0]['to'] - $rangeArray[0]['from'] + 1);
                }
            } else {
                OC_Response::setContentLengthHeader($fileSize);
            }
        }
        header('Content-Type: ' . $type, true);
    }

    /**
     * Copy from OC_Files
     * @var string
     */
    private static $multipartBoundary = '';

    /**
     * @return string
     */
    private static function getBoundary()
    {
        if (empty(self::$multipartBoundary)) {
            self::$multipartBoundary = md5(mt_rand());
        }
        return self::$multipartBoundary;
    }

    /**
     * Copy from OC_Files
     * @param string $rangeHeaderPos
     * @param int    $fileSize
     * @return array $rangeArray ('from'=>int,'to'=>int), ...
     */
    private static function parseHttpRangeHeader($rangeHeaderPos, $fileSize)
    {
        $rArray    = explode(',', $rangeHeaderPos);
        $minOffset = 0;
        $ind       = 0;

        $rangeArray = array();

        foreach ($rArray as $value) {
            $ranges = explode('-', $value);
            if (is_numeric($ranges[0])) {
                if ($ranges[0] < $minOffset) { // case: bytes=500-700,601-999
                    $ranges[0] = $minOffset;
                }
                if ($ind > 0 && $rangeArray[$ind - 1]['to'] + 1 == $ranges[0]) { // case: bytes=500-600,601-999
                    $ind--;
                    $ranges[0] = $rangeArray[$ind]['from'];
                }
            }

            if (is_numeric($ranges[0]) && is_numeric($ranges[1]) && $ranges[0] < $fileSize && $ranges[0] <= $ranges[1]) {
                // case: x-x
                if ($ranges[1] >= $fileSize) {
                    $ranges[1] = $fileSize - 1;
                }
                $rangeArray[$ind++] = array('from' => $ranges[0], 'to' => $ranges[1], 'size' => $fileSize);
                $minOffset          = $ranges[1] + 1;
                if ($minOffset >= $fileSize) {
                    break;
                }
            } elseif (is_numeric($ranges[0]) && $ranges[0] < $fileSize) {
                // case: x-
                $rangeArray[$ind++] = array('from' => $ranges[0], 'to' => $fileSize - 1, 'size' => $fileSize);
                break;
            } elseif (is_numeric($ranges[1])) {
                // case: -x
                if ($ranges[1] > $fileSize) {
                    $ranges[1] = $fileSize;
                }
                $rangeArray[$ind++] = array(
                    'from' => $fileSize - $ranges[1],
                    'to'   => $fileSize - 1,
                    'size' => $fileSize,
                );
                break;
            }
        }
        return $rangeArray;
    }

}
