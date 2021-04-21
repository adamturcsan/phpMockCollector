<?php
require_once 'vendor/autoload.php';


use Symfony\Component\HttpFoundation\Request;

$test = awaitCall("hellophp", 'GET',60);
if($test){
    print $test->getPathInfo();
    print $test->getMethod();
}


function awaitCall($path, $methode = 'GET', $timeout = 1200): Request{ //1200 Seconds is 20 Minutes
    $host = "http://pmc.test/";
    try{
        $ctx = stream_context_create(array('http'=>
            array(
                'timeout' => $timeout,
                "header" => "X-timeout: $timeout\r\n"
            )
        ));

        $rawresult = file_get_contents($host."getCallPayload/".$methode.'/'.$path, false, $ctx);
        $result = json_decode($rawresult,true);
        $req = unserialize(base64_decode($result['request']));
        $data = $result['adddata'];
        //var_dump($data);
        //ToTo return a class that includes request + adddata.
    }
    catch(Exception $e){
        print $e;
        throw new Exception("failed");
    }
    return $req;
}
