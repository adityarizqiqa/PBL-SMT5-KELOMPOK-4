<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware()
    ->withExceptions()
    ->create();
