<?php

function customController(\Symfony\Component\HttpFoundation\Request $request): array{
    print "Hallo PHP to ".$request->getPathInfo();
    return array("key" => "value");
}
