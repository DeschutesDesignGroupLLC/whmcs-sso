<?php

namespace App\Http\Controllers;

class ClientController extends Controller
{
    /**
     * @return void
     */
    public function index()
    {
        $this->redirectService->redirectToClientArea();
    }
}
