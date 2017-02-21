<?php
namespace Lib\Template;

use Lib\View\View;

abstract class Template implements Layout
{
    public static function get()
    {
        return new static();
    }

    public abstract function render(View $content);
}
