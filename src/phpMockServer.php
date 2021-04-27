<?php

namespace dagsta\pms;

require_once "customCallbackInterface.php";


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class phpMockServer
{
    const MOCK_KEY_RULES = 'rules';
    const MOCK_KEY_CUSTOM_CALLBACK = 'customCallback';
    const MOCK_KEY_LATENCY = 'latency';
    /* I would rename it to `headers` */
    const MOCK_KEY_HEADER = 'header';
    const MOCK_KEY_HTTPCODE = 'httpcode';
    const MOCK_KEY_BODY = 'body';
    private $request;
    private $response;
    private const FETCHCALL = 1;
    private const MOCKCALL = 2;

    private $configBasePath;

    function __construct(string $configBasePath)
    {
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
        $this->configBasePath = $configBasePath;
    }

    public function run(): void
    {
        if ($this->determineRequestType() == self::FETCHCALL) {
            $this->performCallFetch();
        } else {
            $this->performMockRequest();
        }
        $this->response->send();
    }

    protected function performCallFetch(): void
    {
        $this->readMockedRequest();
    }

    private function getConfigPath(): string
    {
        $parts = explode("/", $this->getPath());
        if ($parts[count($parts) - 1] == "") {
            unset($parts[count($parts) - 1]);
        }
        $path = implode("/", $parts);
        return $this->configBasePath . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . "mock.json";
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
                $this->response->setContent("Failed to call customCallbock:" . $conf[self::MOCK_KEY_CUSTOM_CALLBACK] . PHP_EOL . $e->getMessage());
                return false;
            }

            $this->storeMockRequest($adddata);
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

    protected function selectMatchingConfig()
    {
        $config = $this->getMockConfig();
        $methode = $this->getMethode();
        if (isset($config[$methode])) {
            foreach ($config[$methode] as $key => $mock) {
                /* I think we have to order mocks by presence of rules and take not the first without rules, but rather the one with matching rule */
                if (!isset($mock[self::MOCK_KEY_RULES]) or $this->checkRules($mock[self::MOCK_KEY_RULES])) {
                    return $mock;
                }
            }
        }
        return false;
    }

    /**
     * could be moved to the RequestDispatcher class
     *
     * RequestDispatcher
     * accepts request (or takes from globals)
     * returns object with:
     * - request type
     * - request method
     * - request path
     */
    private function determineRequestType(): int
    {
        $parts = explode("/", $this->request->getPathInfo());

        if (count($parts) > 3 && $parts[1] == "getCallPayload") {
            return self::FETCHCALL;
        }

        return self::MOCKCALL;
    }

    /**
     * should be moved to the RequestDispatcher class
     */
    private function getMethode()
    {
        if ($this->determineRequestType() == self::FETCHCALL) {
            $parts = explode("/", $this->request->getPathInfo());
            return strtoupper($parts[2]);
        } else {
            return $this->request->getMethod();
        }
    }

    /**
     * should be moved to the RequestDispatcher class
     */
    private function getPath()
    {
        $parts = explode("/", $this->request->getPathInfo());
        if ($this->determineRequestType() == self::FETCHCALL) {
            if ($parts[count($parts) - 1] == "") {
                unset($parts[count($parts) - 1]);
            }
            unset($parts[1]);
            unset($parts[2]);
            return implode("/", $parts);
        }
        return substr($this->request->getPathInfo(), 1);
    }

    private function storeMockRequest($adddata = [])
    {
        $datapath = __DIR__ . '/../data/' . $this->request->getMethod() . DIRECTORY_SEPARATOR
            . $this->getPath();
        if (!file_exists(dirname($datapath))) {
            mkdir(dirname($datapath), 0700, true);
        }
        $data = array("adddata" => $adddata, 'request' => base64_encode(serialize($this->request)));
        file_put_contents($datapath, json_encode($data));
    }

    private function readMockedRequest()
    {
        $configpath = $this->getConfigPath();

        if (!file_exists($configpath)) {
            $this->response->setContent('No Mockconfiguration found for this endpoint');
            $this->response->setStatusCode(404);
            return;
        }
        $datapath = __DIR__ . '/../data/' . $this->getMethode()
            . $this->getPath();
        $timeout = $this->request->headers->get("X-timeout", 60);
        $count = 0;
        while ($count < $timeout && !file_exists($datapath)) {
            sleep(1);
            $count++;
        }
        if ($count == $timeout) {
            $this->response->setContent('Timeout');
            $this->response->setStatusCode(500);
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
