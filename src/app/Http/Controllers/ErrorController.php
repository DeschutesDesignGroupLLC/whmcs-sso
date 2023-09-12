<?php

namespace DeschutesDesignGroupLLC\App\Http\Controllers;

class ErrorController extends Controller
{
    public function index(): array
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
