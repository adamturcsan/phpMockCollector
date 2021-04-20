<?php

function customController(\Symfony\Component\HttpFoundation\Request $request){
    print "Hallo PHP to ".$request->getPathInfo();
}
