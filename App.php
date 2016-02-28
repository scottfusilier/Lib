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
use Lib\Handler\AppErrorHandlerInterface;

class App
{
    /**
     * @var instance The reference to Singleton instance of this class
     */
    private static $instance;

    /**
     * Returns the Singleton instance of this class.
     *
     * @return Singleton of The App instance.
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * App via the `new` operator from outside of this class.
     */
    protected function __construct() {}

    /**
     * Private clone method to prevent cloning of the instance of the
     * App instance.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Private unserialize method to prevent unserializing of the App
     * instance.
     *
     * @return void
     */
    private function __wakeup() {}

    /**
     * Run the Application
     *
     * @param array
     * @return vcid
     */
    public function run(array $routes)
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

        return $this->dispatchController($routeInfo);
    }

    /**
     * Dispatch Controller based on route match
     *
     * @param array
     * @return void
     */
    private function dispatchController(array $routeInfo)
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
            if (AppContainer::isRegistered('AppErrorHandler') && AppContainer::getInstance('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
                ob_start();
                AppContainer::getInstance('AppErrorHandler')->handleNotFound();
                $response->setContent(ob_get_clean());
            } else {
                $response->setContent('');
            }
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
