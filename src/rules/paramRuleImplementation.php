<?php


namespace ALDIDigitalServices\pms\rules;


use ALDIDigitalServices\pms\ruleImplementationInterface;

class paramRuleImplementation implements ruleImplementationInterface
{

    public static function check(\Symfony\Component\HttpFoundation\Request &$request, $rules)
    {
        foreach($rules as $key => $value){
            if($value == "*"){
                if($request->get($key) === null){
                    return false;
                }
            }else{
                if($request->get($key) !== $value){
                    return false;
                }
            }
        }
        return true;
    }
}
