<?php
namespace Lib\View;

use Lib\View\ViewInterface;

/*
 * View Class
 *
 * Use factory, example: Foo::get()->render();
 *
 * Use the setVars() function to set the variables (set up for chaining)
 * example: Foo::get()->setVars(['someChildVariable' => $value])->render();
 */

abstract class View implements ViewInterface
{
    // basic data variable
    protected $data;

    // render data
    abstract public function render();

    // factory, example: Foo::get()->render();
    public static function get()
    {
        return new static();
    }

    // data variables setter, key => value
    public function setVars(array $vars = [])
    {
        foreach ($vars as $key => $value) {
            $this->{$key} = $value;
        }
        return $this; // chaining
    }
}
