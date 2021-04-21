<?php
require_once 'vendor/autoload.php';
//require_once 'phpMockServer.php';

use Symfony\Component\HttpFoundation\Request;

$ms = new \dagsta\pms\phpMockServer();
$ms->run();
