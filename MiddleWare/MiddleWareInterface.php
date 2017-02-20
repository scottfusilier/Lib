<?php
namespace Lib\MiddleWare;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddleWareInterface
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Callable $next);
}
