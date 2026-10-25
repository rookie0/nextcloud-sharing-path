<?php

namespace OCA\SharingPath\Controller;

use OCA\SharingPath\AppInfo\Application;
use OCA\SharingPath\Http\ByteRange;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
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

    public function __construct($appName, IRequest $request, IConfig $config,
                                IUserManager $userManager, IManager $shareManager,
                                IRootFolder $rootFolder, LoggerInterface $logger,
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
        $this->fail(404);
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
    public function handle($uid, $path)
    {
        $stream = null;
        try {
            $user = $this->userManager->get($uid);
            if (!$user || !$user->isEnabled() || !self::validPath($path)) {
                throw new NotFoundException();
            }
            $enabled = $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_ENABLE);
            $personal = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_ENABLE);
            if ($personal === 'no' || ($personal !== 'yes' && $enabled !== 'yes')) {
                $this->fail(403);
            }

            $folder = $this->rootFolder->getUserFolder($uid);
            $node = $folder->get($path);
            if (!$node instanceof File) {
                throw new NotFoundException();
            }
            // Authorize the resolved node, never a raw URL prefix before resolution.
            $relative = $folder->getRelativePath($node->getPath());
            if (!is_string($relative) || !self::validPath($relative)) {
                throw new NotFoundException();
            }
            $relative = trim($relative, '/');
            $publicFolder = $this->config->getUserValue($uid, Application::APP_ID, Application::SETTINGS_KEY_SHARING_FOLDER)
                ?: $this->config->getAppValue(Application::APP_ID, Application::SETTINGS_KEY_DEFAULT_SHARING_FOLDER);
            $publicFolder = trim($publicFolder, '/');
            $inPublicFolder = self::validPath($publicFolder)
                && strpos($relative . '/', $publicFolder . '/') === 0;
            if (!$inPublicFolder && !$this->isShared($uid, $relative)) {
                throw new NotFoundException();
            }

            $size = (int) $node->getSize();
            $mime = $this->mimeTypeDetector->getSecureMimeType($node->getMimeType());
            $head = $this->request->getMethod() === 'HEAD';
            // Without a matching validator, If-Range must produce the full representation.
            $ranges = (!$head && $this->request->getHeader('If-Range') === '')
                ? ByteRange::parse($this->request->getHeader('Range'), $size) : null;
            if ($ranges === []) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                header('Content-Length: 0');
                header('Cache-Control: no-store');
                exit;
            }
            $ranges = $ranges ?? [];
            if (!$head) {
                $stream = $node->fopen('r');
                if (!is_resource($stream)) {
                    throw new NotFoundException();
                }
                if ($ranges && @fseek($stream, $ranges[0]['from']) !== 0) {
                    // Reopen to guarantee a full response starts at byte zero.
                    fclose($stream);
                    $stream = $node->fopen('r');
                    if (!is_resource($stream)) {
                        throw new NotFoundException();
                    }
                    $ranges = [];
                }
            }
            $boundary = 'sharingpath-' . bin2hex(random_bytes(16));
            $this->sendHeaders($mime, $size, $ranges, $boundary);
            if ($head) {
                exit;
            }
            while (ob_get_level() > 0) {
                if (!@ob_end_clean()) {
                    break;
                }
            }
            if (count($ranges) > 1) {
                foreach ($ranges as $index => $range) {
                    if ($index > 0 && fseek($stream, $range['from']) !== 0) {
                        throw new \RuntimeException('Unable to seek download stream');
                    }
                    echo "--{$boundary}\r\nContent-Type: {$mime}\r\n"
                        . "Content-Range: bytes {$range['from']}-{$range['to']}/{$size}\r\n\r\n";
                    self::streamData($stream, $range['to'] - $range['from'] + 1);
                    echo "\r\n";
                }
                echo "--{$boundary}--\r\n";
            } else {
                self::streamData($stream, $ranges ? $ranges[0]['to'] - $ranges[0]['from'] + 1 : null);
            }
            fclose($stream);
            exit;
        } catch (NotFoundException | NotPermittedException $error) {
            if (is_resource($stream)) fclose($stream);
            $this->fail(404);
        } catch (\Throwable $error) {
            if (is_resource($stream)) fclose($stream);
            $this->logger->error('Sharing Path download failed', ['app' => Application::APP_ID, 'exception' => $error]);
            $this->fail(500);
        }
    }

    private function fail(int $status): void
    {
        // Do not disclose whether a private file exists or append text to a partial download.
        if (!headers_sent()) {
            $message = $status === 404 ? "File not found or not publicly accessible.\n"
                : ($status === 403 ? "Access denied.\n" : "Unable to read file.\n");
            http_response_code($status);
            header_remove('Content-Range');
            header_remove('Accept-Ranges');
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Length: ' . strlen($message));
            header('Cache-Control: no-store');
            if ($this->request->getMethod() !== 'HEAD') echo $message;
        }
        exit;
    }

    private static function validPath(string $path): bool
    {
        if (trim($path, '/') === '' || strpos($path, "\0") !== false || strpos($path, '\\') !== false) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }

    private function isShared(string $uid, string $path): bool
    {
        $folder = $this->rootFolder->getUserFolder($uid);
        $parts = explode('/', $path);
        $shared = false;
        for ($length = count($parts); $length > 0; $length--) {
            $node = $folder->get(implode('/', array_slice($parts, 0, $length)));
            foreach ($this->shareManager->getSharesBy($uid, IShare::TYPE_LINK, $node) as $share) {
                // Keep restrictions effective even when several link shares exist.
                if ($share->getHideDownload() || $share->getPassword()
                    || !($share->getPermissions() & Constants::PERMISSION_READ)
                    || ($share->getExpirationDate() && $share->getExpirationDate()->getTimestamp() <= time())) {
                    return false;
                }
                $shared = true;
            }
        }
        return $shared;
    }

    private function sendHeaders(string $mime, int $size, array $ranges, string $boundary): void
    {
        http_response_code($ranges ? 206 : 200);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . (count($ranges) > 1 ? 'multipart/byteranges; boundary=' . $boundary : $mime));
        if ($ranges) {
            header('Accept-Ranges: bytes');
            if (count($ranges) === 1) {
                header("Content-Range: bytes {$ranges[0]['from']}-{$ranges[0]['to']}/{$size}");
                header('Content-Length: ' . ($ranges[0]['to'] - $ranges[0]['from'] + 1));
            }
        } elseif ($size >= 0) {
            header('Content-Length: ' . $size);
        }
    }

    private static function streamData($stream, ?int $bytes): void
    {
        while (!feof($stream) && ($bytes === null || $bytes > 0)) {
            $data = fread($stream, $bytes === null ? 524288 : min(524288, $bytes));
            if ($data === false || ($data === '' && !feof($stream))) {
                throw new \RuntimeException('Unable to read download stream');
            }
            echo $data;
            if ($bytes !== null) {
                $bytes -= strlen($data);
            }
            flush();
        }
        if ($bytes !== null && $bytes > 0) {
            throw new \RuntimeException('Unexpected end of download stream');
        }
    }
}
