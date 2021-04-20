How to use:

just start a Webserver that rewrite every request to index.php

After that you can define mocks in the mockfolder:

If you want to create a mock for /hello/world create the folders /hello/world and place a mock.json in it.

In this json file you can define different methodes (GET, POST, PUT, ...) and define the static response. If you need it more flexible you can define a customcontroller php file ( see mocks/hellophp/mock.json ) that includes a function named customController that expect a \Symfony\Component\HttpFoundation\Request . In this controller you than can define a custom response. This function should return an array with additional key value pairs that could be returned to the codeception test.

For every request to the mock server you can await the payload + additional data with the awaitcall function in clientDemo.php (This has to be transfered to a codeception module). YOu can define a path , a methode (GET, POST, PUT, ...) and the timeout to wait for the response.
