<?php
namespace ALDIDigitalServices\pms;

interface ruleImplementationInterface
{
    public static function check(\Symfony\Component\HttpFoundation\Request &$request, $rules);
}
