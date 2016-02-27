<?php
namespace Lib\Template;

use Lib\View\BasicView;

abstract class Template implements Layout
{
    public static function get()
    {
        return new static();
    }

    public abstract function render(BasicView $content);
}
