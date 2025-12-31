<?php

declare(strict_types=1);

namespace App\Cms\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MethodOverrideMiddleware implements MiddlewareInterface
{
    /**
     * Process an incoming server request to override the HTTP method.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();

        if (strtoupper($method) === 'POST') {
            $newMethod = null;

            // Check X-HTTP-Method-Override header
            $header = $request->getHeaderLine('X-HTTP-Method-Override');
            if (!empty($header)) {
                $newMethod = $header;
            } else {
                // Check boedy for _method
                $body = $request->getParsedBody();
                error_log("MethodOverride - Method: $method, Body: " . print_r($body, true));
                if (is_array($body) && !empty($body['_method'])) {
                    $newMethod = $body['_method'];
                }
            }

            if ($newMethod) {
                $newMethod = strtoupper($newMethod);
                if (in_array($newMethod, ['GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE', 'PATCH'])) {
                    $request = $request->withMethod($newMethod);
                }
            }
        }

        return $handler->handle($request);
    }
}
