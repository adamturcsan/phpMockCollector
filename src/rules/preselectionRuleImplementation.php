<?php


namespace ALDIDigitalServices\pms\rules;


use ALDIDigitalServices\pms\ruleImplementationInterface;

class preselectionRuleImplementation implements ruleImplementationInterface
{
    /**
     * @uses \ALDIDigitalServices\pms\phpMockServer::X_RAY_MOCK_HEADER
     */
    const X_RAY_MOCK_HEADER = 'x-ray-mock-header';

    public static function check(\Symfony\Component\HttpFoundation\Request &$request, $rules)
    {
        $preselectionValue = self::readPreselection(
            $request->getMethod(),
            $request->headers->get(static::X_RAY_MOCK_HEADER),
            substr($request->getPathInfo(), 1)
        );
        if(!$preselectionValue)
        {
            return false;
        }

        if($rules != $preselectionValue){
            return false;
        }

        return true;
    }

    protected static function readPreselection($methode, $xRayHeader, $path)
    {
        if (strlen($xRayHeader) > 0) {
            $xRayHeader = "/" . $xRayHeader;
        }

        $datapath = __DIR__ . '/../../data/' . $methode . $xRayHeader . "/"
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
