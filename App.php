<?php
namespace Lib;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\Dispatcher\GroupCountBased as DispatcherGroupCountBased;
use Lib\Container\AppContainer;

class App
{
    public static function run($routes)
    {
        $routeCollector = new RouteCollector(new Std, new GroupCountBased);
        foreach ($routes as $route) {
            $routeCollector->addRoute($route[0], $route[1], $route[2]);
        }

        $request = Request::createFromGlobals();
        AppContainer::register($request);

        $httpMethod = $request->getMethod();
        $uri = parse_url($request->getRequestUri(), PHP_URL_PATH);

        $dispatcher = new DispatcherGroupCountBased($routeCollector->getData());
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        return self::dispatchController($routeInfo);
    }

    private static function dispatchController(array $routeInfo)
    {
        $response = new Response();
        AppContainer::register($response);

        if ($routeInfo[0] == Dispatcher::METHOD_NOT_ALLOWED) {
            $allowedMethods = $routeInfo[1];
            $response->setStatusCode(405);
            $response->setContent('');
            $response->send();
            exit();
        }

        if ($routeInfo[0] == Dispatcher::NOT_FOUND) {
            $response->setStatusCode(404);
            $response->setContent('');
            $response->send();
            exit();
        }

        if ($routeInfo[0] == Dispatcher::FOUND) {
            // collect all vars
            $vars = $routeInfo[2];
            if (!empty($_POST)) {
            $vars += $_POST;
            }
            if (!empty($_GET)) {
            $vars += $_GET;
            }
            unset($_POST);
            unset($_GET);
            // get handler for route
            $handler = $routeInfo[1];
            $info = explode('::', $handler);
            $namespace = $info[0];
            $action = $info[1];
        }

        $controller = new $namespace();
        ob_start();

        $controller->{$action}($vars);

        $response->setContent(ob_get_clean());

        return $response->send();
    }
}
