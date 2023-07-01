<?php

namespace App\Http\Controllers;

class Controller
{
    /**
     * Dispatch request.
     *
     * @param  string  $action
     * @param  array  $parameters
     * @return array
     */
    public function dispatch($action, $parameters)
    {
        if (! $action) {
            $action = 'index';
        }

        $controller = new static();

        if (is_callable([$controller, $action])) {
            return $controller->$action($parameters);
        }
    }
}
