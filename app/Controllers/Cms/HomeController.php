<?php

declare(strict_types=1);

namespace App\Controllers\Cms;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use MonkeysLegion\Template\Renderer;
use Laminas\Diactoros\Response\HtmlResponse;

class HomeController
{
    private Renderer $renderer;

    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->renderer->render('home/index');
        return new HtmlResponse($html);
    }
}
