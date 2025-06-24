<?php

namespace App\Core;

class Application
{
    protected Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function run(): void
    {
        $this->router->dispatch();
    }
}