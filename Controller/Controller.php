<?php
namespace Lib\Controller;

use Lib\Container\AppContainer;
/*
 * Base controller class.
 *
 */
abstract class Controller
{

/*
 * return a 404 response
 */
    protected function fourOhFour()
    {
        AppContainer::getInstance('Response')->setStatusCode(404);
    }

/*
 * return a 401 response
 */
    protected function unAuthorized()
    {
        AppContainer::getInstance('Response')->setStatusCode(401);
    }

/*
 *  wrapper for the header function
 */
    protected function redirect($location = '/')
    {
        AppContainer::getInstance('Response')->setStatusCode(302)->headers->set('Location', $location);
    }
}
