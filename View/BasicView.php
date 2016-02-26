<?php
namespace Lib\View;
/*
 * BasicView Class
 *
 * Use factory, example: Foo::view()->render();
 *
 * Use the setVars() function to set the variables (set up for chaining)
 * example: Foo::view()->setVars(['someChildVariable' => $value])->render();
 */

abstract class BasicView
{
    // basic data variable
    protected $data;

    // render data
    abstract public function render();

    // factory, example: Foo::view()->render();
    public static function view()
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
