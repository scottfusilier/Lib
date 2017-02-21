<?php
namespace Lib\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddlewareInterface
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Callable $next);
}
