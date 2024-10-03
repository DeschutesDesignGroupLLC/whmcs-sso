<?php

namespace DeschutesDesignGroupLLC\App\Services;

class ClientService
{
    public function addClient(array $data = []): mixed
    {
        return localAPI('AddClient', $data);
    }
}
