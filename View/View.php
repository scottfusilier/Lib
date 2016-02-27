<?php
namespace Lib\View;

interface View
{
    public static function get();

    public function render();

    public function setVars(array $vars);
}
