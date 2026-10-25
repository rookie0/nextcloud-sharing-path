<?php
namespace OCP { interface IRequest {} interface IConfig {} interface IGroupManager {} }
namespace OCP\AppFramework { class Controller { protected $request; function __construct($name,$request) { $this->request=$request; } } class Http { const STATUS_FORBIDDEN=403; const STATUS_BAD_REQUEST=400; } }
namespace OCP\AppFramework\Http { class JSONResponse { function __construct(public $data=[],public $status=200) {} } }
namespace OCA\SharingPath\AppInfo { class Application {
 const APP_ID='sharingpath'; const SETTINGS_KEY_DEFAULT_ENABLE='default_enabled'; const SETTINGS_KEY_ENABLE='enabled'; const SETTINGS_KEY_DEFAULT_COPY_PREFIX='default_copy_prefix'; const SETTINGS_KEY_COPY_PREFIX='copy_prefix'; const SETTINGS_KEY_DEFAULT_SHARING_FOLDER='default_sharing_folder'; const SETTINGS_KEY_SHARING_FOLDER='sharing_folder';
} }
namespace {
 require __DIR__ . '/../lib/Controller/SettingsController.php';
 class Request implements \OCP\IRequest { function __construct(public $type){} function getParam($key) { return $this->type; } }
 class Config implements \OCP\IConfig { public $writes=[]; function setAppValue(...$args) { $this->writes[]=['app',$args]; } function setUserValue(...$args) { $this->writes[]=['user',$args]; } }
 class Groups implements \OCP\IGroupManager { function __construct(public $admin){} function isAdmin($id) { return $this->admin; } }
 $old=false; $results=[];
 foreach (['enable'=>'yes','setCopyPrefix'=>'https://example.invalid/test','setSharingFolder'=>'public'] as $method=>$value) {
  foreach ([false,true] as $admin) foreach (['admin','personal'] as $type) {
   $r=new Request($type); $c=new Config(); $g=new Groups($admin);
   $controller=$old ? new \OCA\SharingPath\Controller\SettingsController($r,$c,'sample') : new \OCA\SharingPath\Controller\SettingsController($r,$c,$g,'sample');
   $response=$controller->$method($value);
   $expectForbidden=!$admin && $type==='admin';
   $secure=$expectForbidden ? ($response->status===403 && count($c->writes)===0) : ($response->status===200 && count($c->writes)===1 && $c->writes[0][0]===($type==='admin'?'app':'user'));
   $results[]=['method'=>$method,'admin'=>$admin,'type'=>$type,'status'=>$response->status,'writes'=>count($c->writes),'secure'=>$secure];
  }
 }
 echo json_encode(['source'=>'SettingsController','cases'=>$results,'passed'=>count(array_filter($results,fn($x)=>$x['secure'])),'total'=>count($results)],JSON_PRETTY_PRINT),"\n";
}
