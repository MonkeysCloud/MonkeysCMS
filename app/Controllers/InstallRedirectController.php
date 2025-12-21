<?php

declare(strict_types=1);

namespace App\Controllers;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class InstallRedirectController
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new RedirectResponse('/');
    }
}
