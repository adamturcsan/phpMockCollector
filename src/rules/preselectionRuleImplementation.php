<?php


namespace ALDIDigitalServices\pms\rules;


use ALDIDigitalServices\pms\ruleImplementationInterface;

class preselectionRuleImplementation implements ruleImplementationInterface
{
    /**
     * @uses \ALDIDigitalServices\pms\phpMockServer::HEADER_X_TRACKING_REQUEST_ID
     */
    const HEADER_X_TRACKING_REQUEST_ID = 'x-tracking-request-id';

    public static function check(\Symfony\Component\HttpFoundation\Request &$request, $rules)
    {
        $preselectionValue = self::readPreselection(
            $request->getMethod(),
            substr($request->getPathInfo(), 1),
            $request->headers->get(static::HEADER_X_TRACKING_REQUEST_ID)
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

    protected static function readPreselection($methode, $path, ?string $trackingRequestId = null)
    {
        $trackingRequestIdWithSlash = "";
        if ($trackingRequestId !== null && strlen($trackingRequestId) > 0) {
            $trackingRequestIdWithSlash = "/" . $trackingRequestId;
        }

        $datapath = __DIR__ . '/../../data/' . $methode
            . $trackingRequestIdWithSlash
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
