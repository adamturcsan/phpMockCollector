<?php
require_once 'vendor/autoload.php';

$ms = new \dagsta\pms\phpMockServer(__DIR__ . '/mocks');
$ms->run();
