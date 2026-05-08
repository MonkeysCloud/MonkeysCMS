<?php

declare(strict_types=1);

namespace App\Cms\Form;

use MonkeysLegion\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * FormSecurityMiddleware — Auto-injects CSRF tokens into HTML responses.
 *
 * After the controller renders a response:
 *  1. Injects `<meta name="csrf-token">` into `<head>` for MonkeysJS usage.
 *  2. Injects `<input type="hidden" name="_csrf">` into every `<form` with POST/PUT/PATCH/DELETE.
 *
 * This means templates NEVER need to manually include CSRF tokens.
 */
final class FormSecurityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionManager $session,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Only process HTML responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html') && !$contentType === '') {
            return $response;
        }

        $body = (string) $response->getBody();

        // Skip empty or non-HTML bodies
        if ($body === '' || !str_contains($body, '<')) {
            return $response;
        }

        $token = $this->session->token();
        if ($token === '') {
            return $response;
        }

        $modified = false;

        // 1. Inject <meta name="csrf-token"> into <head>
        if (str_contains($body, '</head>') && !str_contains($body, 'name="csrf-token"')) {
            $meta = '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
            $body = str_replace('</head>', $meta . "\n</head>", $body);
            $modified = true;
        }

        // 2. Inject CSRF into every POST/PUT/PATCH/DELETE form that doesn't already have it
        $body = $this->injectCsrfIntoForms($body, $token, $modified);

        if ($modified) {
            // Create a new response body with the modified HTML
            $stream = $response->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            // Create new stream with modified body
            $newStream = \MonkeysLegion\Http\Message\Stream::createFromString($body);

            return $response
                ->withBody($newStream)
                ->withoutHeader('Content-Length');
        }

        return $response;
    }

    /**
     * Inject CSRF hidden input into form tags.
     *
     * Skips forms that already contain a `name="_csrf"` input
     * (from manual @include or FormBuilder).
     */
    private function injectCsrfIntoForms(string $html, string $token, bool &$modified): string
    {
        $csrfInput = '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';

        // Split by form boundaries to check each form's contents
        $parts = preg_split('/(<form\b[^>]*>)/si', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) <= 1) {
            return $html;
        }

        $result = '';
        for ($i = 0, $count = count($parts); $i < $count; $i++) {
            $part = $parts[$i];

            // Check if this part is a <form> tag
            if (preg_match('/^<form\b/i', $part)) {
                // Check if method is POST/PUT/PATCH/DELETE
                if (preg_match('/method\s*=\s*["\']?(POST|PUT|PATCH|DELETE)["\']?/i', $part)) {
                    // Look ahead: check if the form body (next part) already has _csrf
                    $formBody = $parts[$i + 1] ?? '';
                    if (!str_contains($formBody, 'name="_csrf"') && !str_contains($formBody, "name='_csrf'")) {
                        $modified = true;
                        $result .= $part . "\n" . $csrfInput;
                        continue;
                    }
                }
            }

            $result .= $part;
        }

        return $result;
    }
}
