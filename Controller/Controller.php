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

/*
 * JSON response convenience method
 */
    protected function json(Response $response,$content)
    {
        $response->getBody()->write(json_encode($content));
        return $response->withHeader('Content-Type','application/json; charset=utf-8');
    }
}
