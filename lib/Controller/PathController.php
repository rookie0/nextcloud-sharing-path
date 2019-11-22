<?php

namespace OCA\SharingPath\Controller;

use OC;
use OC_Response;
use OC_Template;
use OC\Files\Filesystem;
use OC\Share\Share;
use OC_Util;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Lock\ILockingProvider;

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

	const FILE = 1;
	const ZIP_FILES = 2;
	const ZIP_DIR = 3;
	const UPLOAD_MIN_LIMIT_BYTES = 1048576; // 1 MiB

	private static $multipartBoundary = '';

	/**
	 * @return string
	 */
	private static function getBoundary() {
		if (empty(self::$multipartBoundary)) {
			self::$multipartBoundary = md5(mt_rand());
		}
		return self::$multipartBoundary;
	}

	/**
	 * @param string $rangeHeaderPos
	 * @param int $fileSize
	 * @return array $rangeArray ('from'=>int,'to'=>int), ...
	 */
	private static function parseHttpRangeHeader($rangeHeaderPos, $fileSize) {
		$rArray=explode(',', $rangeHeaderPos);
		$minOffset = 0;
		$ind = 0;
		$rangeArray = array();
		foreach ($rArray as $value) {
			$ranges = explode('-', $value);
			if (is_numeric($ranges[0])) {
				if ($ranges[0] < $minOffset) { // case: bytes=500-700,601-999
					$ranges[0] = $minOffset;
				}
				if ($ind > 0 && $rangeArray[$ind-1]['to']+1 == $ranges[0]) { // case: bytes=500-600,601-999
					$ind--;
					$ranges[0] = $rangeArray[$ind]['from'];
				}
			}
			if (is_numeric($ranges[0]) && is_numeric($ranges[1]) && $ranges[0] < $fileSize && $ranges[0] <= $ranges[1]) {
				// case: x-x
				if ($ranges[1] >= $fileSize) {
					$ranges[1] = $fileSize-1;
				}
				$rangeArray[$ind++] = array( 'from' => $ranges[0], 'to' => $ranges[1], 'size' => $fileSize );
				$minOffset = $ranges[1] + 1;
				if ($minOffset >= $fileSize) {
					break;
				}
			}
			elseif (is_numeric($ranges[0]) && $ranges[0] < $fileSize) {
				// case: x-
				$rangeArray[$ind++] = array( 'from' => $ranges[0], 'to' => $fileSize-1, 'size' => $fileSize );
				break;
			}
			elseif (is_numeric($ranges[1])) {
				// case: -x
				if ($ranges[1] > $fileSize) {
					$ranges[1] = $fileSize;
				}
				$rangeArray[$ind++] = array( 'from' => $fileSize-$ranges[1], 'to' => $fileSize-1, 'size' => $fileSize );
				break;
			}
		}
		return $rangeArray;
	}

	/**
	 * @param string $filename
	 * @param array $rangeArray ('from'=>int,'to'=>int), ...
	 */
	private static function sendHeaders($filename, array $rangeArray) {
		header('Content-Transfer-Encoding: binary', true);
		header('Pragma: public');// enable caching in IE
		header('Expires: 0');
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		$fileSize = \OC\Files\Filesystem::filesize($filename);
		$type = \OC::$server->getMimeTypeDetector()->getSecureMimeType(\OC\Files\Filesystem::getMimeType($filename));
		if ($fileSize > -1) {
			if (!empty($rangeArray)) {
				http_response_code(206);
				header('Accept-Ranges: bytes', true);
				if (count($rangeArray) > 1) {
					$type = 'multipart/byteranges; boundary='.self::getBoundary();
					// no Content-Length header here
				}
				else {
					header(sprintf('Content-Range: bytes %d-%d/%d', $rangeArray[0]['from'], $rangeArray[0]['to'], $fileSize), true);
					OC_Response::setContentLengthHeader($rangeArray[0]['to'] - $rangeArray[0]['from'] + 1);
				}
			}
			else {
				OC_Response::setContentLengthHeader($fileSize);
			}
		}
		header('Content-Type: '.$type, true);
	}

	/**
	 * @param View $view
	 * @param string $dir
	 * @param array $params ; 'head' boolean to only send header of the request ; 'range' http range header
	 */
	private static function getSingleFile($view, $filename, $params) {
		OC_Util::obEnd();
		$view->lockFile($filename, ILockingProvider::LOCK_SHARED);

		$rangeArray = array();
		if (isset($params['range']) && substr($params['range'], 0, 6) === 'bytes=') {
			$rangeArray = self::parseHttpRangeHeader(substr($params['range'], 6), 
				\OC\Files\Filesystem::filesize($filename));
		}

		if (\OC\Files\Filesystem::isReadable($filename)) {
			self::sendHeaders($filename, $rangeArray);
		} elseif (!\OC\Files\Filesystem::file_exists($filename)) {
			http_response_code(404);
			$tmpl = new OC_Template('', '404', 'guest');
			$tmpl->printPage();
			exit();
		} else {
			http_response_code(403);
			die('403 Forbidden');
		}
		if (isset($params['head']) && $params['head']) {
			return;
		}
		if (!empty($rangeArray)) {
			try {
				if (count($rangeArray) == 1) {
					$view->readfilePart($filename, $rangeArray[0]['from'], $rangeArray[0]['to']);
				}
				else {
					// check if file is seekable (if not throw UnseekableException)
					// we have to check it before body contents
					$view->readfilePart($filename, $rangeArray[0]['size'], $rangeArray[0]['size']);
					$type = \OC::$server->getMimeTypeDetector()->getSecureMimeType(\OC\Files\Filesystem::getMimeType($filename));
					foreach ($rangeArray as $range) {
						echo "\r\n--".self::getBoundary()."\r\n".
							"Content-type: ".$type."\r\n".
							"Content-range: bytes ".$range['from']."-".$range['to']."/".$range['size']."\r\n\r\n";
						$view->readfilePart($filename, $range['from'], $range['to']);
					}
					echo "\r\n--".self::getBoundary()."--\r\n";
				}
			} catch (\OCP\Files\UnseekableException $ex) {
				// file is unseekable
				header_remove('Accept-Ranges');
				header_remove('Content-Range');
				http_response_code(200);
				self::sendHeaders($filename, array());
				$view->readfile($filename);
			}
		}
		else {
			$output = $view->readfile($filename);
		}
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
	public function index($uid, $path)
	{
		// check user exist
		$user = $this->userManager->get($uid);
		if (! $user) {
			http_response_code(404);
			exit;
		}

		$userFolder = $this->rootFolder->getUserFolder($uid);

		try {
			$file = $userFolder->get($path);

			// check share permission
			$shares = $this->shareManager->getSharesBy($uid, Share::SHARE_TYPE_LINK, $file);
			$share  = $shares[0] ?? null;
			if (! $share ||
				$share->getPassword() ||
				($share->getExpirationDate() && $share->getExpirationDate()->getTimestamp() < time())) {
				http_response_code(403);
				exit;
			}

			// todo version file handle

			$filename = $userFolder->getRelativePath($file->getPath());

			\OC_Util::setupFS($uid);
			$params = array();
			/**
			 * Http range requests support
			 */
			if (isset($_SERVER['HTTP_RANGE'])) {
				$params['range'] = \OC::$server->getRequest()->getHeader('Range');
			}

			$view = \OC\Files\Filesystem::getView();
			// create fake root, so that app works for logged in users different than owner of the share
			$fakeRoot = dirname(dirname(dirname($userFolder->getFullPath($file->getPath()))));
			$view->chroot($fakeRoot);

			$executionTime = (int)OC::$server->getIniWrapper()->getNumeric('max_execution_time');

			try {
				if (!\OC\Files\Filesystem::is_dir($filename)) {
					self::getSingleFile($view, $filename, is_null($params) ? array() : $params);
					exit;
				}
				set_time_limit($executionTime);
				$view->unlockFile($filename, ILockingProvider::LOCK_SHARED);
			} catch (\OCP\Lock\LockedException $ex) {
				$view->unlockFile($filename, ILockingProvider::LOCK_SHARED);
				OC::$server->getLogger()->logException($ex);
				$l = \OC::$server->getL10N('core');
				$hint = method_exists($ex, 'getHint') ? $ex->getHint() : '';
				\OC_Template::printErrorPage($l->t('File is currently busy, please try again later'), $hint);
			} catch (\OCP\Files\ForbiddenException $ex) {
				$view->unlockFile($filename, ILockingProvider::LOCK_SHARED);
				OC::$server->getLogger()->logException($ex);
				$l = \OC::$server->getL10N('core');
				\OC_Template::printErrorPage($l->t('Can\'t read file'), $ex->getMessage());
			} catch (\Exception $ex) {
				$view->unlockFile($filename, ILockingProvider::LOCK_SHARED);
				OC::$server->getLogger()->logException($ex);
				$l = \OC::$server->getL10N('core');
				$hint = method_exists($ex, 'getHint') ? $ex->getHint() : '';
				\OC_Template::printErrorPage($l->t('Can\'t read file'), $hint);
			}
			exit;
		} catch (NotFoundException $e) {
			http_response_code(404);
			exit;
		}
	}
}
?>
