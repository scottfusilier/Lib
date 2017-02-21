<?php
namespace Lib\Controller;

use GuzzleHttp\Psr7\ServerRequest as Request;
use GuzzleHttp\Psr7\Response as Response;
use Lib\Container\AppContainer;

abstract class Controller
{

/*
 * Redirect convenience method
 */
    protected function redirect(Response $response, $location = '/')
    {
        return $response->withStatus(302)->withHeader('Location', $location);
    }
}
