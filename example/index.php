<?php
require_once '../vendor/autoload.php';

$ms = new \ALDIDigitalServices\pms\phpMockServer(__DIR__ . '/mocks');
$ms->run();
