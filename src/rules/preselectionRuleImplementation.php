<?php


namespace ALDIDigitalServices\pms\rules;


use ALDIDigitalServices\pms\ruleImplementationInterface;

class preselectionRuleImplementation implements ruleImplementationInterface
{
    /**
     * @uses \ALDIDigitalServices\pms\phpMockServer::HEADER_X_MOCK_REQUEST_ID
     */
    const HEADER_X_MOCK_REQUEST_ID = 'x-mock-request-id';

    public static function check(\Symfony\Component\HttpFoundation\Request &$request, $rules)
    {
        $preselectionValue = self::readPreselection(
            $request->getMethod(),
            $request->headers->get(static::HEADER_X_MOCK_REQUEST_ID),
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

    protected static function readPreselection($methode, $xMockRequestId, $path)
    {
        $xMockRequestIdWithSlash = "";
        if (strlen($xMockRequestId) > 0) {
            $xMockRequestIdWithSlash = "/" . $xMockRequestId;
        }

        $datapath = __DIR__ . '/../../data/' . $methode
            . $xMockRequestIdWithSlash
            . "/" . $path . ".psdat";
        if(file_exists($datapath))
        {
            $value = file_get_contents($datapath);
            unlink($datapath);
            return $value;
        }
        return false;
    }
}
