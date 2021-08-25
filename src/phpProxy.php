<?php


namespace ALDIDigitalServices\pms;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Nyholm\Psr7\Factory\Psr17Factory;




class phpProxy
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function performRequest(Request $request, $targetUri)
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $psrHttpFactory->createRequest($request);

        $targetUri = new Uri($targetUri);
        $psrRequest = $psrRequest->withUri($targetUri);

        $psrResponse = $this->client->send($psrRequest);
        $httpFoundationFactory = new HttpFoundationFactory();
        $response = $httpFoundationFactory->createResponse($psrResponse);
        return $response;
    }
}
