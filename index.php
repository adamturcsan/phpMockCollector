<?php
require_once 'vendor/autoload.php';


use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();
$parts = explode("/", $request->getPathInfo());
if(count($parts) > 3 && $parts[1] == "getCallPayload")
{
    $methode = strtolower($parts[2]);
    if($parts[count($parts)-1] == ""){
        unset($parts[count($parts)-1]);
    }
    unset($parts[1]);
    unset($parts[2]);
    $path = implode("/",$parts);
    $configpath = 'mocks'.$path;
    if(!file_exists($configpath)){
        header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
        exit("NOT FOUND");
    }
    $datapath = 'data/'.$methode.DIRECTORY_SEPARATOR.$path;
    $timeout = $request->headers->get("X-timeout", 60);

    $count = 0;
    while($count < $timeout && !file_exists($datapath)){
        sleep(1);
        $count++;
    }
    if($count == $timeout) {
        exit("TIMEOUT ".$timeout);
    }
    print file_get_contents($datapath);
    unlink($datapath);
}
else {
    $methode = strtolower($request->getMethod());
    if ($parts[count($parts) - 1] == "") {
        unset($parts[count($parts) - 1]);
    }
    $path = implode("/", $parts);
    $configpath = 'mocks' . $path . DIRECTORY_SEPARATOR . "mock.json";
    if (!file_exists($configpath)) {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found", true, 404);
        exit("Not Found");
    }
    $datapath = 'data/' . $methode . DIRECTORY_SEPARATOR . $path;

    if (!file_exists(dirname($datapath))) {
        mkdir(dirname($datapath), 0700, true);
    }

    file_put_contents($datapath, base64_encode(serialize($request)));

    $config = json_decode(file_get_contents($configpath), true);
    if (!isset($config[$methode])) {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found", true, 404);
        exit("Not Found");
    }
    $conf = $config[$methode][0];
    if (isset($conf['customcontroller'])) {
        require_once dirname($configpath).DIRECTORY_SEPARATOR.$conf['customcontroller'].".php";
        customController($request);
    } else {
        if (isset($conf['latency'])) {
            sleep($conf['latency']);
        }
        if (isset($conf['header'])) {
            foreach ($conf['header'] as $header) {
                if (!isset($header['code'])) {
                    $header['code'] = null;
                }
                header($header['content'], true, $header['code']);
            }
        }
        print $conf['body'];
    }
}


