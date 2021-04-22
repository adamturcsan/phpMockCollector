<?php
namespace dagsta\pms;

require_once "customCallbackInterface.php";


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class phpMockServer
{
    private $request;
    private $response;
    private const FETCHCALL = 1;
    private const MOCKCALL = 2;
    function __construct() {
        $this->request = Request::createFromGlobals();
        $this->response= new Response();
    }
    function run(): void {
        if($this->determineRequestType() == self::FETCHCALL){
            $this->performCallFetch();
        } else{
          $this->performMockRequest();
        }
        $this->response->send();
    }

    function performCallFetch(): void{
        $this->readMockedRequest();
    }

    private function getConfigPath(): string{
        $parts = explode("/", $this->getPath());
        if ($parts[count($parts) - 1] == "") {
            unset($parts[count($parts) - 1]);
        }
        $path = implode("/", $parts);
        return __DIR__.'/../mocks/' . $path . DIRECTORY_SEPARATOR . "mock.json";
    }

    function performMockRequest(): bool{
        $conf = $this->selectMatchingConfig();
        if($conf === false){
            $this->response->setContent('No Mock found for this endpoint');
            $this->response->setStatusCode(404);
            return false;
        }
        if (isset($conf['customCallback'])) {
            $classFilePath = dirname($this->getConfigPath()) . DIRECTORY_SEPARATOR . $conf['customCallback'] . ".php";
            try {
                if (file_exists($classFilePath)) {
                    require_once $classFilePath;
                    if (class_exists($conf['customCallback'])) {
                        $controller = new $conf['customCallback']();
                        $adddata = $controller->run($this->request, $this->response);
                    }
                }
            }
            catch(\Exception $e){
                $this->response->setContent("Failed to call customCallbock:".$conf['customCallback'].PHP_EOL.$e->getMessage());
                return false;
            }
        } else {
            if (isset($conf['latency'])) {
                sleep($conf['latency']);
            }
            if (isset($conf['header'])) {
                $this->response->headers->add($conf['header']);
            }
            if(isset($conf['httpcode'])){
                $this->response->setStatusCode($conf['httpcode']);
            }
            if(is_array($conf['body']))
            {
                $this->response->headers->set('Content-Type', 'application/json');
                $this->response->setContent(json_encode($conf['body']));
            }
            else{
                $this->response->setContent($conf['body']);
            }
            $adddata = [];
        }
        $this->storeMockRequest($adddata);
        return true;
    }

    private function checkRules($rules): bool{
        foreach($rules as $type => $ruleset) {
            $classname = __NAMESPACE__.'\\rules\\' . strtolower($type) . "RuleImplementation";
            if (class_exists($classname)){
                if (!$classname::check($this->request, $ruleset)) {
                    return false;
                }
            }
            else{
                //ToDo Display Error?!?
                return false;
            }

        }
        return true;
    }
    
    function selectMatchingConfig(){
        $config = $this->getMockConfig();
        $methode = $this->getMethode();
        if(isset($config[$methode]))
        {
            foreach($config[$methode] as $key => $mock){
                if(!isset($mock['rules']) OR $this->checkRules($mock['rules']))
                {
                    return $mock;
                }
            }
        }
        return false;
    }

    private function determineRequestType(): int {
        $parts = explode("/", $this->request->getPathInfo());
        if(count($parts) > 3 && $parts[1] == "getCallPayload") {
            return self::FETCHCALL;
        }else{
            return self::MOCKCALL;
        }
    }

    private function getMethode() {
        if($this->determineRequestType() == self::FETCHCALL){
            $parts = explode("/", $this->request->getPathInfo());
            return strtoupper($parts[2]);
        }
        else{
            return $this->request->getMethod();
        }
    }

    private function getPath() {
        $parts = explode("/", $this->request->getPathInfo());
        if($this->determineRequestType() == self::FETCHCALL){
            if($parts[count($parts)-1] == ""){
                unset($parts[count($parts)-1]);
            }
            unset($parts[1]);
            unset($parts[2]);
            return implode("/",$parts);
        }
        return substr($this->request->getPathInfo(),1);
    }

    private function storeMockRequest($adddata = []){
        $datapath = __DIR__.'../data/'.$this->request->getMethod().DIRECTORY_SEPARATOR
            .$this->getPath();
        if (!file_exists(dirname($datapath))) {
            mkdir(dirname($datapath), 0700, true);
        }
        $data = array("adddata" => $adddata, 'request' => base64_encode(serialize($this->request)));
        file_put_contents($datapath, json_encode($data));
    }

    private function readMockedRequest(){
        $configpath = $this->getConfigPath();
        if(!file_exists($configpath)){
            $this->response->setContent('No Mockconfiguration found for this endpoint');
            $this->response->setStatusCode(404);
            return;
        }
        $datapath = __DIR__.'../data/'.$this->request->getMethod().DIRECTORY_SEPARATOR
            .$this->getPath();
        $timeout = $this->request->headers->get("X-timeout", 60);
        $count = 0;
        while($count < $timeout && !file_exists($datapath)){
            sleep(1);
            $count++;
        }
        if($count == $timeout) {
            $this->response->setContent('Timeout');
            $this->response->setStatusCode(500);
        }
        $this->response->setContent(file_get_contents($datapath));
        $this->response->setStatusCode(200);
        unlink($datapath);
    }

    private function getMockConfig(){
        $configpath = $this->getConfigPath();
        if (!file_exists($configpath)) {
            return false;
        }
        else{
            return json_decode(file_get_contents($configpath),true);
        }
    }


}
