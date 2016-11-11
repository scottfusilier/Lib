<?php
namespace Lib\Handler;

interface AppAuthHandlerInterface
{
    public function handleAuth($controller, $action, $vars);
}
