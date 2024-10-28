<?php

namespace ALDIDigitalServices\pms;

require_once "customCallbackInterface.php";


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class phpMockServer
{
    const HEADER_X_TRACKING_REQUEST_ID = 'x-tracking-request-id';
    const MOCK_KEY_RULES = 'rules';
    const MOCK_KEY_CUSTOM_CALLBACK = 'customCallback';
    const MOCK_KEY_PROXY_PATH = 'proxyPath';
    const MOCK_KEY_LATENCY = 'latency';
    const MOCK_KEY_HEADER = 'header';
    const MOCK_KEY_HTTPCODE = 'httpcode';
    const MOCK_KEY_BODY = 'body';
    private $request;
    private $response;
    private const FETCHCALL = 1;
    private const MOCKCALL = 2;
    private const RETURNPRESELECTION = 3;

    private $configBasePath;
    private $pathParams;

    function __construct(string $configBasePath)
    {
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
        $this->configBasePath = $configBasePath;
        $this->pathParams = [];
    }

    public function run(): void
    {
        if ($this->determineRequestType() == self::FETCHCALL) {
            $this->performCallFetch();
        } else if ($this->determineRequestType() == self::RETURNPRESELECTION) {
            $this->performPreselection();
        } else{
            $this->performMockRequest();
        }
        $this->response->send();
    }

    protected function performCallFetch(): void
    {
        $this->readMockedRequest();
    }

    protected function performPreselection(): void
    {
        $this->storePreselection();
    }

    private function getConfigPath($onlyRelativPath = false): string
    {
        $parts = explode("/", $this->getPath());
        if ($parts[count($parts) - 1] == "") {
            unset($parts[count($parts) - 1]);
        }
        do{
            $path = implode("/", $parts);
            $filepath = $this->configBasePath . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . "mock.json";
            array_pop($parts);
        }while(count($parts) > 0 && !file_exists($filepath));
        if($onlyRelativPath){
            return $path;
        }
        else{
            return $filepath;
        }

    }

    protected function performMockRequest(): bool
    {
        $conf = $this->selectMatchingConfig();
        if ($conf === false) {
            $this->response->setContent('No Mock found for this endpoint');
            $this->response->setStatusCode(404);
            return false;
        }

        if (isset($conf[self::MOCK_KEY_CUSTOM_CALLBACK])) {
            $classFilePath = dirname($this->getConfigPath()) . DIRECTORY_SEPARATOR . $conf[self::MOCK_KEY_CUSTOM_CALLBACK] . ".php";
            try {
                if (file_exists($classFilePath)) {
                    require_once $classFilePath;
                    if (class_exists($conf[self::MOCK_KEY_CUSTOM_CALLBACK])) {
                        $controller = new $conf[self::MOCK_KEY_CUSTOM_CALLBACK]();
                        if ($controller instanceof customCallbackInterface) {
                            $adddata = $controller->run($this->request, $this->response);
                        }
                    }
                }

                /* we need as much validation as possible - along the way or when mock is being chosen */
                if (!isset($controller)) {
                    throw new \Exception('No callback found.');
                }
            } catch (\Exception $e) {
                $this->response->setContent("Failed to call customCallback:" . $conf[self::MOCK_KEY_CUSTOM_CALLBACK] . PHP_EOL . $e->getMessage());
                return false;
            }

            $this->storeMockRequest($adddata);
            return true;
        }

        else if(isset($conf[self::MOCK_KEY_PROXY_PATH])){
            $phpProxy = new \ALDIDigitalServices\pms\phpProxy();
            $this->response = $phpProxy->performRequest($this->request,$conf[self::MOCK_KEY_PROXY_PATH]);
            return true;
        }

        if (isset($conf[self::MOCK_KEY_LATENCY])) {
            sleep($conf[self::MOCK_KEY_LATENCY]);
        }
        if (isset($conf[self::MOCK_KEY_HEADER])) {
            $this->response->headers->add($conf[self::MOCK_KEY_HEADER]);
        }
        if (isset($conf[self::MOCK_KEY_HTTPCODE])) {
            $this->response->setStatusCode($conf[self::MOCK_KEY_HTTPCODE]);
        }
        if (is_array($conf[self::MOCK_KEY_BODY])) {
            /* I would expect this to be moved to headers inside mock, and add additional key `bodyIsJson` by which encode the body. */
            $this->response->headers->set('Content-Type', 'application/json');
            $this->response->setContent(json_encode($conf[self::MOCK_KEY_BODY]));
        } else {
            $this->response->setContent($conf[self::MOCK_KEY_BODY]);
        }

        $this->storeMockRequest();
        return true;
    }

    private function checkRules($rules): bool
    {
        foreach ($rules as $type => $ruleset) {
            $classname = __NAMESPACE__ . '\\rules\\' . strtolower($type) . "RuleImplementation";
            if (class_exists($classname) && new $classname instanceof ruleImplementationInterface) {
                if (!$classname::check($this->request, $ruleset)) {
                    return false;
                }
            } else {
                //ToDo Display Error?!? /* I'd say - yes, error is required here */
                return false;
            }

        }
        return true;
    }

    protected function validatePath($configPath, $regexPatternPath): bool{
        $matches = [];
        if(preg_match("|".$configPath.$regexPatternPath."|",$this->request->getPathInfo(),$matches) > 0)
        {
            $this->pathParams = $matches;
            return true;
        }
        return false;
    }

    protected function selectMatchingConfig()
    {
        $config = $this->getMockConfig();
        $method = $this->getMethod();
        if(isset($config['path'])){

            foreach ($config = $config['path'] as $path){
                if($this->validatePath($this->getConfigPath(true),$path['route']))
                {
                    $config = $path;
                    break;
                }
            }
        }

        if (isset($config[$method])) {
            foreach ($config[$method] as $key => $mock) {
                if (!isset($mock[self::MOCK_KEY_RULES]) or $this->checkRules($mock[self::MOCK_KEY_RULES])) {
                    return $mock;
                }
            }
        }
        return false;
    }

    private function determineRequestType(): int
    {
        $parts = explode("/", $this->request->getPathInfo());


        if (count($parts) > 3 && $parts[1] == "getCallPayload") {
            return self::FETCHCALL;
        }
        if (count($parts) > 3 && $parts[1] == "returnPreselection") {
            return self::RETURNPRESELECTION;
        }


        return self::MOCKCALL;
    }

    private function getTrackingRequestId(string $resultPrefix = '', string $resultPostfix = ''): ?string
    {
        if ($this->request->headers->has(static::HEADER_X_TRACKING_REQUEST_ID)) {
            return $resultPrefix . $this->request->headers->get(static::HEADER_X_TRACKING_REQUEST_ID) . $resultPostfix;
        }

        return "";
    }

    private function getMethod()
    {
        if ($this->determineRequestType() != self::MOCKCALL) {
            $parts = explode("/", $this->request->getPathInfo());
            return strtoupper($parts[2]);
        } else {
            return $this->request->getMethod();
        }
    }

    private function getPath()
    {
        $parts = explode("/", $this->request->getPathInfo());
        if ($this->determineRequestType() != self::MOCKCALL) {
            if ($parts[count($parts) - 1] == "") {
                unset($parts[count($parts) - 1]);
            }
            unset($parts[1]);
            unset($parts[2]);
            return implode("/", $parts);
        }
        return substr($this->request->getPathInfo(), 1);
    }

    private function storePreselection(): void
    {
        $datapath = __DIR__ . '/../data/' . $this->request->getMethod() . DIRECTORY_SEPARATOR
            . $this->getTrackingRequestId('', DIRECTORY_SEPARATOR)
            . $this->getPath().".psdat";
        $this->createDirectory(dirname($datapath));
        $data = $this->request->get("value");
        if(!$data)
        {
            return;
        }
        file_put_contents($datapath, $data);
    }

    private function storeMockRequest($adddata = [])
    {
        $datapath = __DIR__ . '/../data/' . $this->request->getMethod() . DIRECTORY_SEPARATOR
            . $this->getTrackingRequestId('', DIRECTORY_SEPARATOR)
            . $this->getPath().".dat";
        $this->createDirectory(dirname($datapath));
        //As Symfony request object is lazy with POST content we need to read it once ...
        $this->request->getContent();
        $data = array("adddata" => $adddata, 'request' => base64_encode(serialize($this->request)));
        file_put_contents($datapath, json_encode($data));
    }

    private function createDirectory($directoryName, int $attempt = 0)
    {
        $attempt++;
        if (!is_dir($directoryName) && !@mkdir($directoryName, 0700, true) && !is_dir($directoryName)) {
            if ($attempt < 10) {
                usleep(200 * $attempt);
                $this->createDirectory($directoryName, $attempt);
                return;
            }
            throw new \RuntimeException(sprintf('Directory "%s" could not be created', $directoryName));
        }
    }

    private function readMockedRequest()
    {
        $configpath = $this->getConfigPath();

        if (!file_exists($configpath)) {
            $this->response->setContent('No Mockconfiguration found for this endpoint');
            $this->response->setStatusCode(404);
            return;
        }

        $datapath = __DIR__ . '/../data/' . $this->getMethod()
            . $this->getTrackingRequestId(DIRECTORY_SEPARATOR, '')
            . $this->getPath().".dat";
        $timeout = $this->request->headers->get("X-timeout", 60);
        $count = 0;
        while ($count < $timeout && !file_exists($datapath)) {
            clearstatcache();
            sleep(1);
            $count++;
        }
        if ($count == $timeout) {
            $this->response->setContent('Timeout');
            $this->response->setStatusCode(500);
            return;
        }
        $this->response->setContent(file_get_contents($datapath));
        $this->response->setStatusCode(200);
        unlink($datapath);
    }

    private function getMockConfig()
    {
        $configpath = $this->getConfigPath();
        if (file_exists($configpath)) {
            return json_decode(file_get_contents($configpath), true);
        }
        return [];
    }
    public function getResponseObject(): Response {
        return $this->response;
    }
}
