<?php
namespace Lib\Template;

use Lib\View\BasicView;

interface Layout
{
    public static function get();
    public function render(BasicView $content);
}
