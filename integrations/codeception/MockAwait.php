<?php
namespace dagsta\pms\integrations\codeception;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Symfony\Component\HttpFoundation\Request;

class MockAwait extends \Codeception\Module
{
    /** @var Request $lastrequest */
    private $lastrequest = null;
    private $adddata = [];
    function seeCallbackBody($body){
        $this->assertEquals($body, $this->lastrequest->getContent());
    }

    function seeCallbackBodyJson($body){
        $this->assertJsonStringEqualsJsonString($body, $this->lastrequest->getContent());
    }

    function seeCallbackParam($key, $value){
        $this->assertTrue($this->lastrequest->get($key) == $value,
            "The parameter $key is not matching $value (is '".$this->lastrequest->get($key)."')");
    }

    function seeCallbackHeader($headername, $value){
        $this->assertTrue($this->lastrequest->headers->get($headername) == $value,
            "The header $headername is not matching $value (is '".$this->lastrequest->headers->get($headername)."')");
    }

    function seeAdditionalDataValue($key, $value)
    {
        if(!isset($this->adddata[$key])){
            $this->fail("The Addition Data with the $key is not set");
            return;
        }
        $this->assertTrue($this->adddata[$key] == $value,
            "The additional Data $key is not matching $value (is '".$this->adddata[$key]."')");
    }

    function awaitCallback($path, $methode = 'GET', $timeout = 1200){ //1200 Seconds is 20 Minutes
        $host = "http://pmc.test/";
        $this->adddata = [];
        try{
            $ctx = stream_context_create(array('http'=>
                array(
                    'timeout' => $timeout,
                    "header" => "X-timeout: $timeout\r\n"
                )
            ));
            $url = $host."getCallPayload/".$methode.'/'.$path;
            $rawresult = file_get_contents($url, false, $ctx);
            $result = json_decode($rawresult,true);
            if(!isset($result['request']) || !isset($result['adddata'])){
                throw  new \Exception("Invalid response from Mock Server");
            }
            $req = unserialize(base64_decode($result['request']));
            $this->adddata = $result['adddata'];
        }
        catch(Exception $e){
            $this->fail($e->getMessage());
        }
        $this->lastrequest = $req;
        $this->assertTrue(is_a($this->lastrequest, 'Symfony\Component\HttpFoundation\Request'));
    }
}
