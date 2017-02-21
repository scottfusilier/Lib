<?php
namespace Lib\View;

interface ViewInterface
{
    public static function get();

    public function render();

    public function setVars(array $vars);
}
