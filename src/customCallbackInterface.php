<?php
namespace dagsta\pms;

interface customCallbackInterface
{
    public function run(\Symfony\Component\HttpFoundation\Request &$request, \Symfony\Component\HttpFoundation\Response &$response);
}
