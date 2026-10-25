<?php
namespace OCP { interface IRequest {} interface IConfig {} interface IUserManager {} class Constants { const PERMISSION_READ=1; } }
namespace OCP\AppFramework { class Controller { protected $request; function __construct($name,$request) { $this->request=$request; } } }
namespace OCP\Files { interface File {} interface IRootFolder {} interface IMimeTypeDetector {} class NotFoundException extends \Exception {} class NotPermittedException extends \Exception {} }
namespace OCP\Share { interface IManager {} interface IShare { const TYPE_LINK=3; } }
namespace Psr\Log { interface LoggerInterface {} }
namespace OCA\SharingPath\AppInfo { class Application {
 const APP_ID='sharingpath'; const SETTINGS_KEY_DEFAULT_ENABLE='default_enabled'; const SETTINGS_KEY_ENABLE='enabled'; const SETTINGS_KEY_DEFAULT_SHARING_FOLDER='default_sharing_folder'; const SETTINGS_KEY_SHARING_FOLDER='sharing_folder';
} }
namespace OCA\SharingPath\Controller {
 function header($s,$replace=true) { [$k,$v]=explode(':',$s,2); $GLOBALS['observed_headers'][strtolower($k)]=trim($v); }
 function header_remove($k) { unset($GLOBALS['observed_headers'][strtolower($k)]); }
 function http_response_code($code) { $GLOBALS['observed_status']=$code; }
 function flush() {}
}
namespace {
 require __DIR__ . '/../lib/Http/ByteRange.php';
 require __DIR__ . '/../lib/Controller/PathController.php';
 $GLOBALS['observed_headers']=[]; $GLOBALS['observed_status']=200;
 register_shutdown_function(function() { fwrite(STDERR,json_encode(['status'=>$GLOBALS['observed_status'],'headers'=>$GLOBALS['observed_headers']])."\n"); });
 class Request implements \OCP\IRequest { function getHeader($name) { return $name==='Range' ? $GLOBALS['argv'][2] : '';  } function getMethod() { return 'GET'; } }
 class Config implements \OCP\IConfig { function getAppValue($app,$key) { return $key==='default_enabled'?'yes':'public'; } function getUserValue(...$args) { return ''; } }
 class Users implements \OCP\IUserManager { function get($uid) { return new class { function isEnabled() { return true; } };  } }
 class Shares implements \OCP\Share\IManager {}
 class Mime implements \OCP\Files\IMimeTypeDetector { function getSecureMimeType($m) { return $m; } }
 class Logger implements \Psr\Log\LoggerInterface { function warning(...$args) {} function error(...$args) { fwrite(STDERR,'ERROR '.json_encode($args)); } }
 class NonSeekable {
  public $context; private $offset=0;
  function stream_open(...$args) { return true; }
  function stream_read($n) { $s=substr('abcdefghij',$this->offset,$n); $this->offset+=strlen($s);return $s; }
  function stream_eof() { return $this->offset>=10; }
  function stream_tell() { return $this->offset; }
  function stream_seek(...$args) { return false; }
  function stream_stat() { return []; }
 }
 stream_wrapper_register('nonseek',NonSeekable::class);
 class File implements \OCP\Files\File {
  function getPath() { return '/fixture/files/public/file.txt'; } function getSize() { return 10; } function getMimeType() { return 'text/plain'; }
  function fopen($mode) { if($GLOBALS['argv'][1]==='openfail')return false;if($GLOBALS['argv'][1]==='nonseek')return fopen('nonseek://fixture','r');$f=fopen('php://temp','r+');fwrite($f,'abcdefghij');rewind($f);return $f; }
 }
 class Folder { function getRelativePath($path) { return 'public/file.txt'; } function get($path) { return new File(); } }
 class Root implements \OCP\Files\IRootFolder { function getUserFolder($uid) { return new Folder(); } }
 $controller=new \OCA\SharingPath\Controller\PathController('sharingpath',new Request(),new Config(),new Users(),new Shares(),new Root(),new Logger(),new Mime());
 $controller->handle('fixture','public/file.txt');
}
