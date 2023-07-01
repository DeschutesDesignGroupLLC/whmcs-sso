<?php

namespace App\Services;

class ClientService
{
    /**
     * @return mixed
     */
    public function addClient($data = [])
    {
        return localAPI('AddClient', $data);
    }
}
