<?php


namespace ALDIDigitalServices\pms\rules;


use ALDIDigitalServices\pms\ruleImplementationInterface;

class preselectionRuleImplementation implements ruleImplementationInterface
{

    public static function check(\Symfony\Component\HttpFoundation\Request &$request, $rules)
    {
        $preselectionValue = self::readPreselection($request->getMethod(),substr($request->getPathInfo(), 1));
        if(!$preselectionValue)
        {
            return false;
        }

        if($rules != $preselectionValue){
            return false;
        }

        return true;
    }

    protected static function readPreselection($methode, $path)
    {
        $datapath = __DIR__ . '/../../data/' . $methode . "/"
            . $path.".psdat";
        if(file_exists($datapath))
        {
            $value = file_get_contents($datapath);
            unlink($datapath);
            return $value;
        }
        return false;
    }
}
