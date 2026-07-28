<?php

namespace OCA\SharingPath\Controller;

use OCA\SharingPath\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class PathController extends Controller
{
    private $config;
    private $userManager;
    private $shareManager;
    private $rootFolder;
    private $logger;
    private $mimeTypeDetector;

    public function __construct($appName,
                                IRequest $request,
                                IConfig $config,
                                IUserManager $userManager,
                                IManager $shareManager,
                                IRootFolder $rootFolder,
                                LoggerInterface $logger,
                                IMimeTypeDetector $mimeTypeDetector)
    {
        parent::__construct($appName, $request);

        $this->config = $config;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->logger = $logger;
        $this->mimeTypeDetector = $mimeTypeDetector;
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @NoSameSiteCookieRequired
     */
    #[PublicPage]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index()
    {
        $this->logger->warning('request index not allowed', ['app' => Application::APP_ID]);
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
    #[PublicPage]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function handle($uid, $path)
    {
        // check user & path exist
        $user = $this->userManager->get($uid);
        if (! $user || ! $path) {
            $this->logger->warning("user or file not exist, user: {$uid}", ['app' => Application::APP_ID]);
            http_response_code(404);
            exit;
        }

        // check user has enabled sharing path
        $enabled = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
        $userEnabled = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_ENABLE);
        if ($userEnabled === 'no' || (! $userEnabled && $enabled !== 'yes')) {
            $this->logger->warning("app not enabled, user enabled: {$userEnabled}, enabled: {$enabled}", ['app' => Application::APP_ID]);
            http_response_code(403);
            exit;
        }

        try {
            $userFolder = $this->rootFolder->getUserFolder($uid);
            $sharingFolder = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);
            $userSharingFolder = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER);

            $sharingFolder = $userSharingFolder ?: $sharingFolder;
            $isPublic = $sharingFolder && str_starts_with(trim($path, '/') . '/', trim($sharingFolder, '/') . '/');
            // check file is under sharing folder or is shared
            if (! $isPublic && ! $this->isShared($uid, $path)) {
                $this->logger->warning("file not public, sharing folder: {$sharingFolder}", ['app' => Application::APP_ID]);
                http_response_code(404);
                exit;
            }

            // todo version file handle

            $node = $userFolder->get($path);
            if (! ($node instanceof File)) {
                // directories cannot be fetched directly
                http_response_code(404);
                exit;
            }

            $fileSize = $node->getSize();
            $mimeType = $this->mimeTypeDetector->getSecureMimeType($node->getMimeType());

            $rangeArray = [];
            if (substr($this->request->getHeader('Range'), 0, 6) === 'bytes=') {
                $rangeArray = self::parseHttpRangeHeader(substr($this->request->getHeader('Range'), 6), $fileSize);
            }

            $this->sendHeaders($mimeType, $fileSize, $rangeArray);

            if ($this->request->getMethod() === 'HEAD') {
                exit;
            }

            // drop any output buffering before streaming file contents
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $stream = $node->fopen('r');
            if (! is_resource($stream)) {
                throw new NotFoundException('unable to open file for reading');
            }

            if (! empty($rangeArray)) {
                if (@fseek($stream, $rangeArray[0]['from']) === -1) {
                    // stream is not seekable, fall back to a full response
                    header_remove('Accept-Ranges');
                    header_remove('Content-Range');
                    http_response_code(200);
                    header('Content-Length: ' . $fileSize, true);
                    self::streamData($stream, null);
                } elseif (count($rangeArray) === 1) {
                    self::streamData($stream, $rangeArray[0]['to'] - $rangeArray[0]['from'] + 1);
                } else {
                    foreach ($rangeArray as $range) {
                        echo "\r\n--" . self::getBoundary() . "\r\n" .
                            'Content-type: ' . $mimeType . "\r\n" .
                            'Content-range: bytes ' . $range['from'] . '-' . $range['to'] . '/' . $range['size'] . "\r\n\r\n";
                        fseek($stream, $range['from']);
                        self::streamData($stream, $range['to'] - $range['from'] + 1);
                    }
                    echo "\r\n--" . self::getBoundary() . "--\r\n";
                }
            } else {
                self::streamData($stream, null);
            }
            fclose($stream);

            // FIXME: The exit is required here because otherwise the AppFramework is trying to add headers as well
            exit;
        } catch (NotFoundException $e) {
            http_response_code(404);
            $this->logger->warning("not found, user: {$uid}, file: {$path}", ['app' => Application::APP_ID]);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->logger->error("server error, user: {$uid}, file: {$path}, message: {$e->getMessage()}", [
                'app'       => Application::APP_ID,
                'exception' => $e,
            ]);
            exit;
        }
    }

    private function isShared($uid, $path)
    {
        $userFolder = $this->rootFolder->getUserFolder($uid);
        $segments = explode('/', trim($path, '/'));
        $len = count($segments);
        $now = time();
        $shared = false;
        for ($i = $len; $i > 0; $i--) {
            $tmpPath = implode('/', array_slice($segments, 0, $i));
            $userPath = $userFolder->get($tmpPath);
            $shares = $this->shareManager->getSharesBy($uid, IShare::TYPE_LINK, $userPath);
            $share = $shares[0] ?? null;

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
     * @param string $mimeType
     * @param int|float $fileSize
     * @param array  $rangeArray ('from'=>int,'to'=>int), ...
     */
    private function sendHeaders(string $mimeType, $fileSize, array $rangeArray)
    {
        header('Content-Transfer-Encoding: binary', true);
        header('Pragma: public');// enable caching in IE
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        $type = $mimeType;
        if ($fileSize > -1) {
            if (! empty($rangeArray)) {
                http_response_code(206);
                header('Accept-Ranges: bytes', true);
                if (count($rangeArray) > 1) {
                    $type = 'multipart/byteranges; boundary=' . self::getBoundary();
                    // no Content-Length header here
                } else {
                    header(sprintf('Content-Range: bytes %d-%d/%d', $rangeArray[0]['from'], $rangeArray[0]['to'], $fileSize), true);
                    header('Content-Length: ' . ($rangeArray[0]['to'] - $rangeArray[0]['from'] + 1), true);
                }
            } else {
                header('Content-Length: ' . $fileSize, true);
            }
        }
        header('Content-Type: ' . $type, true);
    }

    /**
     * Echo data from a stream, optionally limited to $bytes bytes
     * @param resource $stream
     * @param int|float|null $bytes null streams until EOF
     */
    private static function streamData($stream, $bytes)
    {
        $chunkSize = 512 * 1024;
        if ($bytes === null) {
            while (! feof($stream)) {
                $data = fread($stream, $chunkSize);
                if ($data === false) {
                    break;
                }
                echo $data;
                flush();
            }
            return;
        }

        $remaining = $bytes;
        while ($remaining > 0 && ! feof($stream)) {
            $data = fread($stream, (int) min($chunkSize, $remaining));
            if ($data === false || $data === '') {
                break;
            }
            echo $data;
            flush();
            $remaining -= strlen($data);
        }
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
        $rArray = explode(',', $rangeHeaderPos);
        $minOffset = 0;
        $ind = 0;

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
                $minOffset = $ranges[1] + 1;
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


if (! function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return 0 === strncmp($haystack, $needle, \strlen($needle));
    }
}
