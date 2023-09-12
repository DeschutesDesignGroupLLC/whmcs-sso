<?php

namespace DeschutesDesignGroupLLC\App\Http\Controllers;

use DeschutesDesignGroupLLC\App\Services\ClientService;
use DeschutesDesignGroupLLC\App\Services\CookieService;
use DeschutesDesignGroupLLC\App\Services\RedirectService;
use DeschutesDesignGroupLLC\App\Services\SsoService;
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

    public function dispatch(array $parameters = []): array|string
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
