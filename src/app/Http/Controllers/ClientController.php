<?php

namespace DeschutesDesignGroupLLC\App\Http\Controllers;

use JetBrains\PhpStorm\NoReturn;

class ClientController extends Controller
{
    #[NoReturn]
    public function index(): void
    {
        $this->redirectService->redirectToClientArea();
    }
}
