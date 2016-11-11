<?php
namespace Lib\Handler;

interface AppErrorHandlerInterface
{
    public function handleNotFound();

    public function handleAppError(\Exception $e);
}
