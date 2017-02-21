<?php
namespace Lib\Template;

use Lib\View\View;

interface Layout
{
    public static function get();
    public function render(View $content);
}
