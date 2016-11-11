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
use Lib\Handler\AppAuthHandlerInterface;

class App
{
/**
 * reference to Singleton instance of this class
 */
    private static $instance;

/**
 * Returns the Singleton instance of this class.
 *
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
 */
    protected function __construct() {}

/**
 * Private clone method to prevent cloning of the instance of the
 * App instance.
 *
 */
    private function __clone() {}

/**
 * Private unserialize method to prevent unserializing of the App
 * instance.
 *
 */
    private function __wakeup() {}

/**
 * Run the Application
 *
 */
    public function run($routes)
    {
        $routeCollector = $routes(new RouteCollector(new Std, new GroupCountBased));

        $request = Request::createFromGlobals();
        AppContainer::register($request);

        $httpMethod = $request->getMethod();
        $uri = parse_url($request->getRequestUri(), PHP_URL_PATH);

        $dispatcher = new DispatcherGroupCountBased($routeCollector->getData());
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        return $this->dispatchRoute($routeInfo);
    }

/**
 * Dispatch route match
 *
 */
    private function dispatchRoute(array $routeInfo)
    {
        switch ($routeInfo[0]) {
            case Dispatcher::METHOD_NOT_ALLOWED :
                return self::handleMethodNotAllowed($routeInfo[1]);

            case Dispatcher::NOT_FOUND :
                return self::handleNotFound($routeInfo);

            case Dispatcher::FOUND :
                return self::handleFound($routeInfo);
        }
        return self::handleAppError(new \Exception);
    }

    private static function handleAppError(\Exception $e)
    {
        $response = new Response();
        $response->setStatusCode(500);

        if (AppContainer::isRegistered('AppErrorHandler') && AppContainer::getInstance('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
            ob_start();
            AppContainer::getInstance('AppErrorHandler')->handleAppError($e);
            $response->setContent(ob_get_clean());
        } else {
            $response->setContent('');
        }

        return $response->send();
    }

/**
 * HTTP Method Not Allowed
 */
    private function handleMethodNotAllowed(array $routeInfo)
    {
        $response = new Response();
        AppContainer::register($response);

        $allowedMethods = $routeInfo;

        $response->setStatusCode(405);
        $response->setContent('');

        return $response->send();
    }

/**
 * Route Match Not Found
 */
    private function handleNotFound(array $routeInfo)
    {
        $response = new Response();
        AppContainer::register($response);

        $response->setStatusCode(404);

        if (AppContainer::isRegistered('AppErrorHandler') && AppContainer::getInstance('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
            ob_start();
            AppContainer::getInstance('AppErrorHandler')->handleNotFound();
            $response->setContent(ob_get_clean());
        } else {
            $response->setContent('');
        }

        return $response->send();
    }

/**
 * Handle Matched Route
 */
    private static function handleFound($routeInfo)
    {
        $response = new Response();
        AppContainer::register($response);

        // collect all vars
        $vars = $routeInfo[2];
        if (!empty($_POST)) {
            $vars += $_POST;
        }

        if (!empty($_GET)) {
            $vars += $_GET;
        }

        // we handle vars here and pass them into the function call for devs
        unset($_POST);
        unset($_GET);

        // get handler for route
        $handler = $routeInfo[1];

        // route override as closure, call it with variables
        if (is_object($handler) && (new \ReflectionFunction($handler))->isClosure()) {
            $response->setContent($handler($vars));
            return $response->send();
        }

        // handle regular controller based route
        $info = explode('::', $handler);
        $namespace = $info[0];
        $action = $info[1];

        try {
            $controller = new $namespace();
            ob_start();

            if (AppContainer::isRegistered('AppAuthHandler') && AppContainer::getInstance('AppAuthHandler') instanceOf AppAuthHandlerInterface) {
                AppContainer::getInstance('AppAuthHandler')->handleAuth($controller, $action, $vars);
            } else {
                // call with variables
                $controller->{$action}($vars);
            }

            $response->setContent(ob_get_clean());

            return $response->send();
        } catch(\Exception $e) {
            return self::handleAppError($e);
        }
    }
}
