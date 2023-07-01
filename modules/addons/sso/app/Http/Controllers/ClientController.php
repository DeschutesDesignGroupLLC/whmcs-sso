<?php

namespace App\Http\Controllers;

class ClientController extends Controller
{
    /**
     * @return array
     */
    public function onboard()
    {
        return [
            'pagetitle' => 'Onboarding',
            'breadcrumb' => [
                'index.php?m=sso&action=onboard' => 'Onboarding',
            ],
            'templatefile' => 'onboard',
            'requirelogin' => true,
            'vars' => [
                'modulelink' => $vars['modulelink']
            ],
        ];
    }
}
