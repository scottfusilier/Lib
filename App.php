<?php
namespace Lib;

use GuzzleHttp\Psr7\ServerRequest as Request;
use GuzzleHttp\Psr7\Response as Response;
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
    public function run(callable $routes)
    {
        $routeCollector = $routes(new RouteCollector(new Std, new GroupCountBased));

        $request = Request::fromGlobals();

        $httpMethod = $request->getMethod();
        $uri = parse_url($request->getUri(), PHP_URL_PATH);

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

        if (AppContainer::has('AppErrorHandler') && AppContainer::get('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
            $response->getBody()->write((AppContainer::get('AppErrorHandler')->handleAppError($e)));
        }

        return self::sendResponse($response->withStatus(500));
    }

/**
 * HTTP Method Not Allowed
 */
    private static function handleMethodNotAllowed(array $routeInfo)
    {
        $response = new Response();
        //TODO: set header to indicate allowable methods
        $allowedMethods = $routeInfo;

        return self::sendResponse($response->withStatus(405));
    }

/**
 * Route Match Not Found
 */
    private static function handleNotFound(array $routeInfo)
    {
        $response = new Response();

        if (AppContainer::has('AppErrorHandler') && AppContainer::get('AppErrorHandler') instanceOf AppErrorHandlerInterface) {
            $response->getBody()->write(AppContainer::get('AppErrorHandler')->handleNotFound());
        }

        return self::sendResponse($response->withStatus(404));
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

            return self::sendResponse($middleware($request,$response,function(){}));
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
        if (!is_array($handler)) {
            throw new \InvalidArgumentException('route handler must be passed in an array');
        }

        if (is_object($handler[0]) && is_callable($handler[0])) {
            self::pushCallableRoute($routeInfo);
        } else {
            self::pushControllerRoute($routeInfo);
        }

        // add per-route middleware group
        if (!empty($handler[1])) {
            self::pushRouteMiddlewares($handler[1]);
        }

        self::pushGlobalMiddlewares();
    }

/**
 * Push a controller based route to the runtime stack. If middleware group is specified, push the group on the stack as well.
 */
    private static function pushControllerRoute(array $routeInfo)
    {
        $vars = $routeInfo[2];

        $routeHandlerArray = $routeInfo[1];
        AppContainer::register('Route', function() use ($routeHandlerArray){return $routeHandlerArray[0];});
        $info = explode('::', $routeHandlerArray[0]);
        $namespace = $info[0];

        if (!empty($info[1])) {
            // handler class with method call
            $action = $info[1];
            $handlerWrap = function (Request $request, Response $response) use ($namespace, $action, $vars) {
                $controller = new $namespace();
                return $controller->{$action}($request,$response,$vars);
            };
        } else {
            // handler class with __invoke call
            $handlerWrap = function (Request $request, Response $response) use ($namespace, $vars) {
                $controller = new $namespace();
                return $controller($request,$response,$vars);
            };
        }

        return self::pushMiddleware($handlerWrap);

    }

/**
 * Push a callable route to the runtime stack
 */
    private static function pushCallableRoute(array $routeInfo)
    {
        $vars = $routeInfo[2];
        $handlerArray = $routeInfo[1];
        $handler = $handlerArray[0];

        $handlerWrap = function (Request $request, Response $response) use ($handler,$vars) {
            return $handler($request, $response, $vars);
        };

        return self::pushMiddleware($handlerWrap);
    }

/**
 * Apply per-route middleware group to the runtime stack
 */
    private static function pushRouteMiddlewares($groupName)
    {
        if (empty(self::$middlewares[$groupName])) {
            throw new \InvalidArgumentException('no such middleware');
        }

        return self::pushMiddlewares(self::$middlewares[$groupName]); // see self::addMiddlewares
    }

/**
 * Apply global middlewares to the runtime stack if they exist
 */
    private static function pushGlobalMiddlewares()
    {
        if (is_array(self::$globalMiddlewares)) {
            return self::pushMiddlewares(self::$globalMiddlewares);
        }
    }

/**
 * Apply a group of middlewares to the runtime stack
 */
    private static function pushMiddlewares(array $middlewares)
    {
        foreach ($middlewares as $mw) {
            self::pushMiddleware($mw);
        }
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

        self::$middlewareStack = function (Request $request, Response $response, callable $next) use ($oldStack, $Middleware) {
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
    public static function setGlobalMiddlewares(array $value)
    {
         self::$globalMiddlewares = $value;
    }

/**
 * Send the http response
 */
    private static function sendResponse(Response $response)
    {
        if (headers_sent()) {
             throw new RuntimeException('Unable to send response; headers already sent');
        }

        // Status
        header(sprintf('HTTP/%s %s %s',$response->getProtocolVersion(),$response->getStatusCode(),$response->getReasonPhrase()));

        // Headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // send response body stream
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            echo $stream->read(4096);
        }
    }
}
