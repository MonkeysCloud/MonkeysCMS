<?php

declare(strict_types=1);

namespace App\Cms\I18n;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * LocaleMiddleware — Detects locale from URL prefix, cookie, or browser header.
 *
 * Detection priority:
 *   1. URL path prefix (/es/...) — strips prefix for downstream routing
 *   2. Cookie (monkeyscms_lang)
 *   3. Accept-Language header
 *   4. Default language
 *
 * Only active when the multilingual module is enabled.
 * Sets request attribute 'locale' for downstream controllers.
 */
final class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LanguageService $languages,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip if module is disabled
        if (!$this->languages->isEnabled()) {
            $request = $request->withAttribute('locale', $this->languages->getDefaultCode());
            return $handler->handle($request);
        }

        $enabledCodes = $this->languages->getEnabledCodes();
        $defaultCode = $this->languages->getDefaultCode();

        // 1. URL prefix detection
        $path = $request->getUri()->getPath();
        $locale = $this->detectFromPath($path, $enabledCodes);

        if ($locale !== null) {
            // Strip the locale prefix from the URI for downstream routing
            $newPath = preg_replace('#^/' . preg_quote($locale, '#') . '(/|$)#', '/', $path);
            $newPath = $newPath ?: '/';
            $request = $request->withUri($request->getUri()->withPath($newPath));
        }

        // 2. Cookie detection
        if ($locale === null) {
            $cookies = $request->getCookieParams();
            $cookieLang = $cookies['monkeyscms_lang'] ?? null;
            if ($cookieLang !== null && in_array($cookieLang, $enabledCodes, true)) {
                $locale = $cookieLang;
            }
        }

        // 3. Accept-Language header
        if ($locale === null) {
            $locale = $this->detectFromHeader(
                $request->getHeaderLine('Accept-Language'),
                $enabledCodes,
            );
        }

        // 4. Default fallback
        $locale = $locale ?? $defaultCode;

        // Set on request for downstream use
        $request = $request->withAttribute('locale', $locale);
        $request = $request->withAttribute('available_locales', $enabledCodes);

        // Process and set cookie on response
        $response = $handler->handle($request);

        // Set language cookie (1 year)
        $response = $response->withAddedHeader(
            'Set-Cookie',
            "monkeyscms_lang={$locale}; Path=/; Max-Age=31536000; SameSite=Lax",
        );

        return $response;
    }

    /**
     * Detect locale from URL path prefix.
     * Matches /xx/ or /xx at the beginning of the path.
     */
    private function detectFromPath(string $path, array $enabledCodes): ?string
    {
        if (preg_match('#^/([a-z]{2,3})(?:/|$)#', $path, $matches)) {
            $code = $matches[1];
            if (in_array($code, $enabledCodes, true)) {
                return $code;
            }
        }
        return null;
    }

    /**
     * Detect locale from Accept-Language header.
     */
    private function detectFromHeader(string $header, array $enabledCodes): ?string
    {
        if ($header === '') return null;

        // Parse "en-US,en;q=0.9,es;q=0.8" format
        $parts = explode(',', $header);
        $candidates = [];

        foreach ($parts as $part) {
            $part = trim($part);
            $q = 1.0;
            if (preg_match('/;q=([0-9.]+)/', $part, $m)) {
                $q = (float) $m[1];
                $part = trim(explode(';', $part)[0]);
            }
            // Normalize: en-US → en
            $code = strtolower(explode('-', $part)[0]);
            $candidates[$code] = $q;
        }

        // Sort by quality descending
        arsort($candidates);

        foreach ($candidates as $code => $q) {
            if (in_array($code, $enabledCodes, true)) {
                return $code;
            }
        }

        return null;
    }
}
