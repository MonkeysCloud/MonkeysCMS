<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use App\Cms\Auth\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * StartSessionMiddleware - Ensures the session is started for every request
 */
class StartSessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionManager $session,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start the session
        $this->session->start();
        
        // Pass to next handler
        return $handler->handle($request);
    }
}
