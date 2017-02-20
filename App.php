<?php
namespace Lib;

use Symfony\Component\HttpFoundation\Request as Request;
use Symfony\Component\HttpFoundation\Response as Response;
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
 * Reference to Singleton instance of this class
 */
    private static $instance;

/**
 * Runtime middleware stack
 */
    private static $middlewareStack;

/**
 * Middlewares that can be added on a per-route basis
 */
    private static $middlewares;

/**
 * Middlewares added to all routes
 */
    private static $globalMiddlewares;

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

        $httpMethod = $request->getMethod();
        $uri = parse_url($request->getRequestUri(), PHP_URL_PATH);

        $dispatcher = new DispatcherGroupCountBased($routeCollector->getData());
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::METHOD_NOT_ALLOWED :
                return self::handleMethodNotAllowed($routeInfo[1]);

            case Dispatcher::NOT_FOUND :
                return self::handleNotFound($routeInfo);

            case Dispatcher::FOUND :
                return self::handleFound($request, $routeInfo);
        }

        return self::handleAppError(new \Exception);
    }

    private static function handleAppError(\Exception $e)
    {
        $response = new Response();
        $response->setStatusCode(500);

        if (AppContainer::isRegistered('AppErrorHandler') && AppContainer::get('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
            return $response->setContent(AppContainer::get('AppErrorHandler')->handleAppError($e))->send();
        }

        return $response->setContent('')->send();
    }

/**
 * HTTP Method Not Allowed
 */
    private static function handleMethodNotAllowed(array $routeInfo)
    {
        $response = new Response();
        //TODO: set header to indicate allowable, or don't
        $allowedMethods = $routeInfo;

        $response->setStatusCode(405);
        return $response->setContent('')->send();
    }

/**
 * Route Match Not Found
 */
    private static function handleNotFound(array $routeInfo)
    {
        $response = new Response();
        $response->setStatusCode(404);

        if (AppContainer::isRegistered('AppErrorHandler') && AppContainer::get('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
            return $response->setContent(AppContainer::get('AppErrorHandler')->handleNotFound())->send();
        }

        return $response->setContent('')->send();
    }

/**
 * Handle Matched Route
 */
    private static function handleFound(Request $request, array $routeInfo)
    {
        try {
            $response = new Response();

            self::prepareRuntimeStack($routeInfo);

            $middleware = self::$middlewareStack;
            if (!$middleware) {
                throw new \RuntimeException('no middleware registered');
            }

            return $middleware($request,$response,function(){})->send();
        } catch(\Exception $e) {
            return self::handleAppError($e);
        }
    }

/**
 * Orchestrate preparing the runtime stack
 */
    private static function prepareRuntimeStack(array $routeInfo)
    {
        $handler = $routeInfo[1];
        if (is_object($handler) &&  (new \ReflectionFunction($handler))->isClosure()) {
            self::pushCallableRoute($routeInfo);
        } else {
            self::pushControllerRoute($routeInfo);
        }

        self::pushGlobalMiddlewares();
    }

/**
 * Push a controller based route to the runtime stack. If middleware group is specified, push the group on the stack as well.
 */
    private static function pushControllerRoute(array $routeInfo)
    {
        $vars = self::consolidateVars($routeInfo[2]);

        // handle regular controller based route
        $info = explode('::', $routeInfo[1]);
        $namespace = $info[0];
        $action = $info[1];

        $handlerWrap = function ($request, $response) use ($namespace, $action, $vars) {
            $controller = new $namespace();
            return $controller->{$action}($request,$response,$vars);
        };

        self::pushMiddleware($handlerWrap);

        // add per-route middleware group
        if (!empty($info[2])) {
            $middlewares = is_array(self::$middlewares[$info[2]]) ? self::$middlewares[$info[2]] : [];
            foreach ($middlewares as $mw) {
                self::pushMiddleware($mw);
            }
        }
    }

/**
 * Push a callable route to the runtime stack
 */
    private static function pushCallableRoute(array $routeInfo)
    {
        $vars = self::consolidateVars($routeInfo[2]);
        $handler = $routeInfo[1];

        $handlerWrap = function ($request, $response) use ($handler,$vars) {
            return $handler($request, $response, $vars);
        };
        return self::pushMiddleware($handlerWrap);
    }

/**
 * Apply global middlewares to the runtime stack if they exist
 */
    private static function pushGlobalMiddlewares()
    {
        if (is_array(self::$globalMiddlewares)) {
            foreach (self::$globalMiddlewares as $mw) {
                self::pushMiddleware($wm);
            }
        }
    }

/**
 * Collect vars and consolidate
 */
    private static function consolidateVars(array $vars)
    {
        if (!empty($_POST)) {
            $vars += $_POST;
        }
        if (!empty($_GET)) {
            $vars += $_GET;
        }
        unset($_POST);
        unset($_GET);

        return $vars;
    }

/**
 * Push middleware to the runtime stack
 */
    private static function pushMiddleware(callable $Middleware)
    {
        $oldStack = self::$middlewareStack;
        if ($oldStack === null) {
            return self::$middlewareStack = $Middleware;
        }

        self::$middlewareStack = function ($request, $response, callable $next) use ($oldStack, $Middleware) {
            return $Middleware($request, $response, function ($req, $res) use ($next, $oldStack) {
                return $oldStack($req, $res, $next);
            });
        };
    }

/**
 * Add a per-route middleware group
 */
    public static function addMiddlewares($key, array $value)
    {
         self::$middlewares[$key] = $value;
    }

/**
 * Add global middlewares that will be applied to all routes
 */
    public static function setGlobaleMiddlewares(array $value)
    {
         self::$globalMiddlewares = $value;
    }
}
