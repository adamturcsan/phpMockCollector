<?php

if(!class_exists("helloPhpController",false))
{
    class helloPhpController implements \dagsta\pms\customCallbackInterface
    {
        public function run(\Symfony\Component\HttpFoundation\Request &$request, \Symfony\Component\HttpFoundation\Response &$response): array{
            $response->setContent("Hallo PHP to ".$request->getPathInfo());
            return array("key" => "value");
        }
    }
}
