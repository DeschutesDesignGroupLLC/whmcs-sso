<?php

namespace App\Http\Controllers;

class ErrorController extends Controller
{
    /**
     * @return array
     */
    public function index()
    {
        return [
            'pagetitle' => 'Error',
            'breadcrumb' => [
                'index.php?m=sso&controller=error' => 'Error',
            ],
            'templatefile' => 'error',
            'requirelogin' => false,
            'vars' => [
                'error' => $_REQUEST['error'],
            ],
        ];
    }
}
