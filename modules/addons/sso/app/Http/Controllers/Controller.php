<?php

namespace App\Http\Controllers;

use App\Services\ClientService;
use App\Services\CookieService;
use App\Services\RedirectService;
use App\Services\SsoService;
use WHMCS\Authentication\CurrentUser;
use WHMCS\User\User;

class Controller
{
    protected SsoService $ssoService;

    protected ClientService $clientService;

    protected CookieService $cookieService;

    protected RedirectService $redirectService;

    protected ?User $currentUser = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ssoService = new SsoService();
        $this->clientService = new ClientService();
        $this->cookieService = new CookieService();
        $this->redirectService = new RedirectService();

        if ($this->currentUser = (new CurrentUser)->user()) {
            $this->redirectService->redirectToClientArea();
        }
    }

    /**
     * @return array|string
     */
    public function dispatch(array $parameters = [])
    {
        $controller = new static();

        $method = match (true) {
            $_SERVER['REQUEST_METHOD'] === 'POST' => 'store',
            $_SERVER['REQUEST_METHOD'] === 'PUT' => 'update',
            $_SERVER['REQUEST_METHOD'] === 'DELETE' => 'delete',
            default => 'index'
        };

        if (is_callable([$controller, $method])) {
            return $controller->$method($parameters);
        }

        return [];
    }
}
