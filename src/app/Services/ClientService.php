<?php

namespace DeschutesDesignGroupLLC\App\Services;

class ClientService
{
    public function addClient($data = []): mixed
    {
        return localAPI('AddClient', $data);
    }
}
