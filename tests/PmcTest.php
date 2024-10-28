<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;


final class PmcTest extends TestCase
{
    const HEADER_X_TRACKING_REQUEST_ID_FOR_SERVER_ARRAY = 'HTTP_X_TRACKING_REQUEST_ID';

    protected function setUp(): void
    {
        global $_SERVER, $_GET, $_POST, $_REQUEST;
        $_SERVER['USER'] = "vagrant";
        $_SERVER['HOME'] = "/home/vagrant";
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = "de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7";
        $_SERVER['HTTP_ACCEPT_ENCODING'] = "gzip, deflate";
        $_SERVER['HTTP_ACCEPT'] = "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9";
        $_SERVER['HTTP_USER_AGENT'] = "Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.128 Safari/537.36";
        $_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] = "1";
        $_SERVER['HTTP_CONNECTION'] = "keep-alive";
        $_SERVER['HTTP_HOST'] = "pmc.test";
        $_SERVER['SCRIPT_FILENAME'] = "/home/vagrant/pmc/index.php";
        $_SERVER['REDIRECT_STATUS'] = "200";
        $_SERVER['SERVER_NAME'] = "pmc.test";
        $_SERVER['SERVER_PORT'] = "80";
        $_SERVER['SERVER_ADDR'] = "192.168.10.10";
        $_SERVER['REMOTE_PORT'] = "58430";
        $_SERVER['REMOTE_ADDR'] = "192.168.10.1";
        $_SERVER['SERVER_SOFTWARE'] = "nginx/1.18.0";
        $_SERVER['GATEWAY_INTERFACE'] = "CGI/1.1";
        $_SERVER['REQUEST_SCHEME'] = "http";
        $_SERVER['SERVER_PROTOCOL'] = "HTTP/1.1";
        $_SERVER['DOCUMENT_ROOT'] = "/home/vagrant/pmc";
        $_SERVER['DOCUMENT_URI'] = "/index.php";
        $_SERVER['SCRIPT_NAME'] = "/index.php";
        $_SERVER['CONTENT_LENGTH'] = "";
        $_SERVER['CONTENT_TYPE'] = "";
        $_SERVER['QUERY_STRING'] = "";
        $_SERVER['FCGI_ROLE'] = "RESPONDER";
        $_SERVER['PHP_SELF'] = "/index.php";
        $_SERVER['REQUEST_TIME_FLOAT'] = "1619118802.5351";
        $_SERVER['REQUEST_TIME'] = "1619118802";
        $_GET = array();
        $_POST = array();
        $_REQUEST = array_merge($_GET,$_POST);

    }

    protected function cleanRequestFileContent($content): string
    {
        $json = json_decode($content,true);
        $requestString = base64_decode($json["request"]);
        /* @var \Symfony\Component\HttpFoundation\Request $request*/
        $request = unserialize($requestString);
        $params = array("REQUEST_URI" => $request->server->get("REQUEST_URI"));
        $request->server = new \Symfony\Component\HttpFoundation\ServerBag($params);
        $requestString = serialize($request);
        $json["request"] = base64_encode($requestString);
        return trim(json_encode($json));
    }

    public function testCanGetPathForNormalRequest(): void
    {
        $_SERVER['REQUEST_URI'] = "/hello";
        $_SERVER['REQUEST_METHOD'] = "GET";

        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getPath'
        );
        $this->assertEquals("hello", $returnVal);
        $_SERVER['REQUEST_URI'] = "/hello/world";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getPath'
        );
        $this->assertEquals("hello/world", $returnVal);
    }

    public function testCanGetPathForFetchRequest(): void
    {
        $_SERVER['REQUEST_URI'] = "/getCallPayload/GET/hello";
        $_SERVER['REQUEST_METHOD'] = "GET";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getPath'
        );
        $this->assertEquals("/hello", $returnVal);

        $_SERVER['REQUEST_URI'] = "/getCallPayload/GET/hello/world";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getPath'
        );
        $this->assertEquals("/hello/world", $returnVal);
    }

    public function testCangetMethodForFetchRequest(): void
    {
        $_SERVER['REQUEST_URI'] = "/getCallPayload/GET/hello";
        $_SERVER['REQUEST_METHOD'] = "GET";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getMethod'
        );
        $this->assertEquals("GET", $returnVal);

        $_SERVER['REQUEST_URI'] = "/getCallPayload/POST/hello/world";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getMethod'
        );
        $this->assertEquals("POST", $returnVal);
    }

    public function testCangetMethodForMockRequest(): void
    {
        $_SERVER['REQUEST_URI'] = "/hello";
        $_SERVER['REQUEST_METHOD'] = "GET";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getMethod'
        );
        $this->assertEquals("GET", $returnVal);

        $_SERVER['REQUEST_METHOD'] = "POST";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getMethod'
        );
        $this->assertEquals("POST", $returnVal);
    }

    public function testCanMockRequestBeFullfilled(): void
    {
        $_SERVER['REQUEST_URI'] = "/hello";
        $_SERVER['REQUEST_METHOD'] = "GET";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertEquals("Hallo Welt", $m->getResponseObject()->getContent());


    }

    public function testIsParamRuleWorking(): void
    {
        $_SERVER['REQUEST_URI'] = "/hello";
        $_GET['hallo'] = "b";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertEquals("Hallo Welt with hallo param is b", $m->getResponseObject()->getContent());

        $_GET['hallo'] = "a";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertEquals("Hallo Welt with hallo * param", $m->getResponseObject()->getContent());
    }

    public function testIfRequestIsStored(): void {
        $this->assertFileExists(__DIR__."/../data/GET/hello.dat");
        $this->assertEquals($this->cleanRequestFileContent(file_get_contents(__DIR__."/__data/request_hello")), $this->cleanRequestFileContent(file_get_contents(__DIR__."/../data/GET/hello.dat")), "Request is not correct");
    }

    public function testFetchRequest(): void {
        $_SERVER['REQUEST_URI'] = "getCallPayload/GET/hello";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performCallFetch'
        );
        $this->assertEquals($this->cleanRequestFileContent(file_get_contents(__DIR__."/__data/request_hello")), $this->cleanRequestFileContent($m->getResponseObject()->getContent()));
    }

    public function testCanMockRequestBeFullfilledWithXMockRequestId(): void
    {
        $_SERVER['REQUEST_URI'] = "/hello";
        $_SERVER['REQUEST_METHOD'] = "GET";
        $_SERVER[PmcTest::HEADER_X_TRACKING_REQUEST_ID_FOR_SERVER_ARRAY] = 'request_id.test';
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertEquals("Hallo Welt", $m->getResponseObject()->getContent());
    }

    public function testIsParamRuleWorkingWithXMockRequestId(): void
    {
        $_SERVER['REQUEST_URI'] = "/hello";
        $_SERVER[PmcTest::HEADER_X_TRACKING_REQUEST_ID_FOR_SERVER_ARRAY] = 'request_id.test';
        $_GET['hallo'] = "b";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertEquals("Hallo Welt with hallo param is b", $m->getResponseObject()->getContent());

        $_GET['hallo'] = "a";
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertEquals("Hallo Welt with hallo * param", $m->getResponseObject()->getContent());
    }

    public function testIfRequestWithXMockRequestIdIsStored(): void {
        $this->assertFileExists(__DIR__."/../data/GET/request_id.test/hello.dat");
        $this->assertEquals($this->cleanRequestFileContent(file_get_contents(__DIR__."/__data/request_hello_with_request_id")), $this->cleanRequestFileContent(file_get_contents(__DIR__."/../data/GET/request_id.test/hello.dat")), "Request is not correct");
    }

    public function testFetchRequestWithXMockRequestId(): void {
        $_SERVER['REQUEST_URI'] = "getCallPayload/GET/hello";
        $_SERVER[PmcTest::HEADER_X_TRACKING_REQUEST_ID_FOR_SERVER_ARRAY] = 'request_id.test';
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performCallFetch'
        );
        $this->assertEquals($this->cleanRequestFileContent(file_get_contents(__DIR__."/__data/request_hello_with_request_id")), $this->cleanRequestFileContent($m->getResponseObject()->getContent()));
    }
    
    public function testGetConfigPath(): void{
        $_SERVER['REQUEST_URI'] = "/hello";
        $_GET = [];
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getConfigPath'
        );
        $this->assertEquals(realpath($returnVal), realpath(__DIR__."/__mocks/hello/mock.json"));
    }

    public function testGetConfigPathForWildcart(): void{
        $_SERVER['REQUEST_URI'] = "/wildcard/1/hallo";
        $_GET = [];
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'getConfigPath'
        );
        $this->assertEquals(realpath($returnVal), realpath(__DIR__."/__mocks/wildcard/mock.json"));
    }

    public function testGetConfig(): void{
        $_SERVER['REQUEST_URI'] = "/hello";
        $_GET = [];
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'selectMatchingConfig'
        );
        $this->assertEqualsCanonicalizing($returnVal,["header" => ['X-Bla' => "Hallo", "z-bla" => "z-bla"], 'method' => 'GET', "httpcode" => 200, "latency" => 5, "body" => "Hallo Welt"]);
    }

    public function testGetConfigForWildcart(): void{
        $_SERVER['REQUEST_URI'] = "/wildcard/1/hallo";
        $_GET = [];
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'selectMatchingConfig'
        );
        $this->assertEqualsCanonicalizing($returnVal,["header" => ['X-Bla' => "Hallo", "z-bla" => "z-bla"], 'method' => 'GET', "httpcode" => 200,  "body" => "Hallo Wildcard"]);
    }
    public function testGetConfigForSecondWildcart(): void{
        $_SERVER['REQUEST_URI'] = "/wildcard/1/2/hallo";
        $_GET = [];
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'selectMatchingConfig'
        );
        $this->assertEqualsCanonicalizing($returnVal,["header" => ['X-Bla' => "Hallo", "z-bla" => "z-bla"], 'method' => 'GET', "httpcode" => 200,  "body" => "Hallo Wildcard2"]);
    }
    /*public function testProxy(): void
    {
        $_SERVER['REQUEST_URI'] = "/proxy";
        $_SERVER['REQUEST_METHOD'] = "GET";
        $_GET = [];
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'performMockRequest'
        );
        $this->assertJsonStringEqualsJsonString('{"userId": 1,"id": 1,"title": "delectus aut autem","completed": false}', $m->getResponseObject()->getContent());
    }*/

    public function testPreselectionRuleIsWorking(): void
    {
        $_SERVER['REQUEST_URI'] = "/preselection";
        $_GET = array();
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'run'
        );
        $this->assertEquals("Default", $m->getResponseObject()->getContent());

        $_SERVER['REQUEST_URI'] = "returnPreselection/GET/preselection";
        $_GET = array("value"=> "YES");
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'run'
        );

        $_SERVER['REQUEST_URI'] = "/preselection";
        $_GET = array();
        $m = new \ALDIDigitalServices\pms\phpMockServer(__DIR__."/__mocks");
        $returnVal = \ALDIDigitalServices\pms\PHPUnitUtil::callMethod(
            $m,
            'run'
        );
        $this->assertEquals("Preselection", $m->getResponseObject()->getContent());
    }

}
